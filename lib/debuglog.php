<?php

/**
 * DB-backed diagnostic log for managed Yandex Metrica debug mode.
 *
 * The public API intentionally mirrors the first file-based implementation:
 * write/read/clear/formatEntry. This keeps JS, tools endpoints and options UI
 * stable while moving storage out of /upload.
 */
if (!class_exists('UshakovCookieDebugLog')) {

    final class UshakovCookieDebugLog
    {
        public const MODULE_ID = 'ushakov.cookie';
        public const TABLE_NAME = 'b_ushakov_cookie_debug_log';

        private const MAX_ROWS_PER_SITE = 500;

        private static $lastErrorCode = '';
        private static $lastErrorMessage = '';

        public static function normalizeSiteId($siteId): string
        {
            $siteId = preg_replace('/[^a-zA-Z0-9_]/', '', trim((string) $siteId));

            return $siteId !== '' ? $siteId : 's1';
        }

        public static function isEnabled(string $siteId): bool
        {
            $siteId = self::normalizeSiteId($siteId);

            return \Bitrix\Main\Config\Option::get(self::MODULE_ID, 'ym_debug_' . $siteId, 'N') === 'Y';
        }

        public static function write(string $siteId, string $level, string $event, string $message = '', array $details = []): bool
        {
            self::resetLastError();

            $siteId = self::normalizeSiteId($siteId);
            if (!self::isEnabled($siteId)) {
                self::setLastError('DEBUG_DISABLED', 'Debug mode is disabled for site ' . $siteId);
                return false;
            }

            try {
                self::ensureTable();

                $detailsJson = json_encode(self::sanitizeDetails($details), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($detailsJson === false) {
                    $detailsJson = '{}';
                }

                $connection = self::getConnection();
                $helper = $connection->getSqlHelper();
                $connection->queryExecute(
                    'INSERT INTO ' . self::TABLE_NAME . ' (DATE_INSERT, SITE_ID, LEVEL, EVENT, MESSAGE, DETAILS)
                    VALUES (' .
                    "'" . $helper->forSql(date('Y-m-d H:i:s')) . "', " .
                    "'" . $helper->forSql($siteId, 20) . "', " .
                    "'" . $helper->forSql(self::normalizeLevel($level), 10) . "', " .
                    "'" . $helper->forSql(self::normalizeEvent($event), 80) . "', " .
                    "'" . $helper->forSql(self::limitString($message, 500), 500) . "', " .
                    "'" . $helper->forSql($detailsJson) . "'" .
                    ')'
                );

                self::trimSiteRows($siteId);

                return true;
            } catch (\Throwable $e) {
                self::setLastError('WRITE_FAILED', $e->getMessage());
                return false;
            }
        }

        public static function read(string $siteId, int $limit = 100): array
        {
            self::resetLastError();

            $siteId = self::normalizeSiteId($siteId);
            $limit = max(1, min(500, $limit));

            try {
                self::ensureTable();

                $connection = self::getConnection();
                $helper = $connection->getSqlHelper();
                $rows = [];
                $result = $connection->query(
                    'SELECT ID, DATE_INSERT, SITE_ID, LEVEL, EVENT, MESSAGE, DETAILS
                    FROM ' . self::TABLE_NAME . "
                    WHERE SITE_ID = '" . $helper->forSql($siteId) . "'
                    ORDER BY ID DESC
                    LIMIT " . $limit
                );

                while ($row = $result->fetch()) {
                    $rows[] = self::rowToEntry($row);
                }

                return array_reverse($rows);
            } catch (\Throwable $e) {
                self::setLastError('READ_FAILED', $e->getMessage());
                return [];
            }
        }

        public static function clear(string $siteId): bool
        {
            self::resetLastError();

            $siteId = self::normalizeSiteId($siteId);

            try {
                self::ensureTable();

                $connection = self::getConnection();
                $helper = $connection->getSqlHelper();
                $connection->queryExecute(
                    'DELETE FROM ' . self::TABLE_NAME . "
                    WHERE SITE_ID = '" . $helper->forSql($siteId) . "'"
                );

                return true;
            } catch (\Throwable $e) {
                self::setLastError('CLEAR_FAILED', $e->getMessage());
                return false;
            }
        }

        public static function getLastErrorCode(): string
        {
            return self::$lastErrorCode;
        }

        public static function getLastErrorMessage(): string
        {
            return self::$lastErrorMessage;
        }

        public static function formatEntry(array $entry): string
        {
            $ts = self::limitString((string) ($entry['ts'] ?? ''), 40);
            $siteId = self::normalizeSiteId($entry['siteId'] ?? 's1');
            $level = self::normalizeLevel((string) ($entry['level'] ?? 'INFO'));
            $event = self::normalizeEvent((string) ($entry['event'] ?? 'event'));
            $message = self::limitString((string) ($entry['message'] ?? ''), 500);
            $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];
            $detailsJson = '';

            if ($details) {
                $encoded = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $detailsJson = $encoded !== false ? ' ' . self::limitString($encoded, 900) : '';
            }

            return '[' . $ts . '] ' . $siteId . ' ' . $level . ' ' . $event .
                ($message !== '' ? ': ' . $message : '') . $detailsJson;
        }

        private static function ensureTable(): bool
        {
            $connection = self::getConnection();
            if ($connection->isTableExists(self::TABLE_NAME)) {
                return true;
            }

            try {
                $connection->queryExecute(
                    'CREATE TABLE ' . self::TABLE_NAME . ' (
                        ID int(11) NOT NULL AUTO_INCREMENT,
                        DATE_INSERT datetime NOT NULL,
                        SITE_ID varchar(20) NOT NULL,
                        LEVEL varchar(10) NOT NULL,
                        EVENT varchar(80) NOT NULL,
                        MESSAGE varchar(500) NULL,
                        DETAILS text NULL,
                        PRIMARY KEY (ID),
                        KEY IX_USHAKOV_COOKIE_DEBUG_SITE_ID (SITE_ID, ID),
                        KEY IX_USHAKOV_COOKIE_DEBUG_SITE_DATE (SITE_ID, DATE_INSERT)
                    )'
                );
            } catch (\Throwable $e) {
                if (!$connection->isTableExists(self::TABLE_NAME)) {
                    throw $e;
                }
            }

            return true;
        }

        private static function trimSiteRows(string $siteId): void
        {
            $connection = self::getConnection();
            $helper = $connection->getSqlHelper();
            $siteIdSql = $helper->forSql(self::normalizeSiteId($siteId));
            $threshold = $connection->query(
                'SELECT ID
                FROM ' . self::TABLE_NAME . "
                WHERE SITE_ID = '" . $siteIdSql . "'
                ORDER BY ID DESC
                LIMIT " . self::MAX_ROWS_PER_SITE . ', 1'
            )->fetch();

            if (empty($threshold['ID'])) {
                return;
            }

            $connection->queryExecute(
                'DELETE FROM ' . self::TABLE_NAME . "
                WHERE SITE_ID = '" . $siteIdSql . "'
                AND ID <= " . (int) $threshold['ID']
            );
        }

        private static function rowToEntry(array $row): array
        {
            $details = [];
            $detailsRaw = (string) ($row['DETAILS'] ?? '');
            if ($detailsRaw !== '') {
                $decoded = json_decode($detailsRaw, true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }

            return [
                'id' => (int) ($row['ID'] ?? 0),
                'ts' => self::formatDbDate($row['DATE_INSERT'] ?? ''),
                'siteId' => self::normalizeSiteId($row['SITE_ID'] ?? 's1'),
                'level' => self::normalizeLevel((string) ($row['LEVEL'] ?? 'INFO')),
                'event' => self::normalizeEvent((string) ($row['EVENT'] ?? 'event')),
                'message' => self::limitString((string) ($row['MESSAGE'] ?? ''), 500),
                'details' => $details,
            ];
        }

        private static function formatDbDate($value): string
        {
            if ($value instanceof \Bitrix\Main\Type\DateTime) {
                return $value->toString();
            }

            return self::limitString((string) $value, 40);
        }

        private static function getConnection()
        {
            if (method_exists('\Bitrix\Main\Application', 'getConnection')) {
                return \Bitrix\Main\Application::getConnection();
            }

            return \Bitrix\Main\Application::getInstance()->getConnection();
        }

        private static function resetLastError(): void
        {
            self::$lastErrorCode = '';
            self::$lastErrorMessage = '';
        }

        private static function setLastError(string $code, string $message): void
        {
            self::$lastErrorCode = self::limitString($code, 40);
            self::$lastErrorMessage = self::limitString($message, 300);
        }

        private static function normalizeLevel(string $level): string
        {
            $level = strtoupper(trim($level));
            $allowed = ['INFO', 'WARN', 'ERROR', 'SKIP'];

            return in_array($level, $allowed, true) ? $level : 'INFO';
        }

        private static function normalizeEvent(string $event): string
        {
            $event = strtolower(trim($event));
            $event = preg_replace('/[^a-z0-9_.:-]/', '_', $event);

            return self::limitString($event !== '' ? $event : 'event', 80);
        }

        private static function sanitizeDetails(array $details): array
        {
            $result = [];
            foreach ($details as $key => $value) {
                $safeKey = preg_replace('/[^a-zA-Z0-9_.:-]/', '_', (string) $key);
                if ($safeKey === '') {
                    continue;
                }

                if (preg_match('/(url|href|email|phone|ip|user_agent|payload|params)/i', $safeKey)) {
                    $result[$safeKey] = '[redacted]';
                    continue;
                }

                if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
                    $result[$safeKey] = $value;
                    continue;
                }

                if (is_array($value)) {
                    $result[$safeKey] = self::sanitizeDetails(array_slice($value, 0, 20, true));
                    continue;
                }

                $result[$safeKey] = self::limitString((string) $value, 160);
            }

            return $result;
        }

        private static function limitString(string $value, int $limit): string
        {
            $value = trim($value);
            if (function_exists('mb_substr')) {
                return mb_substr($value, 0, $limit);
            }

            return substr($value, 0, $limit);
        }
    }
}
