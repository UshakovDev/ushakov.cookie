<?php
/**
 * AJAX: запись согласия в реестр через API userconsent (совместимо со старым и новым ядром).
 */
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Context;
use Bitrix\Main\SystemException;

header('Content-Type: application/json; charset=UTF-8');

function ushakovCookieNormalizeGuestClientId($value): string
{
    $normalized = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $value);
    if (!is_string($normalized)) {
        return '';
    }

    $normalized = trim($normalized);
    if ($normalized === '' || strlen($normalized) < 16) {
        return '';
    }

    return substr($normalized, 0, 64);
}

function ushakovCookieGetGuestCookieName(string $siteId): string
{
    return 'ushakov_cookie_guest_' . $siteId;
}

function ushakovCookieBuildOriginId(?int $userId, string $siteId, string $guestClientId, string $ip): string
{
    if ($userId !== null && $userId > 0) {
        return 'user:' . $userId;
    }

    if ($guestClientId !== '') {
        return 'guest:' . $siteId . ':' . $guestClientId;
    }

    return 'ip:' . sha1($siteId . '|' . $ip);
}

function ushakovCookieStartSessionIfNeeded(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function ushakovCookieGetOriginSessionFlagKey(int $agreementId, string $originId): string
{
    return 'ushakov_cookie_accept_logged_' . $agreementId . '_' . sha1($originId);
}

function ushakovCookieFindExistingConsent(string $consentTableClass, int $agreementId, string $source, string $originId, ?int $userId, string $ip): ?array
{
    $result = $consentTableClass::getList([
        'filter' => [
            '=AGREEMENT_ID' => $agreementId,
        ],
        'select' => ['ID', 'DATE_INSERT', 'USER_ID', 'IP', 'URL', 'ORIGINATOR_ID', 'ORIGIN_ID'],
        'order'  => ['ID' => 'DESC'],
        'limit'  => 200,
    ]);

    while ($row = $result->fetch()) {
        $rowOriginId = trim((string) ($row['ORIGIN_ID'] ?? ''));
        $rowOriginatorId = trim((string) ($row['ORIGINATOR_ID'] ?? ''));
        $rowIp = trim((string) ($row['IP'] ?? ''));
        $rowUserId = isset($row['USER_ID']) ? (int) $row['USER_ID'] : null;

        if ($rowOriginatorId === $source && $rowOriginId === $originId) {
            return $row;
        }

        // Legacy fallback: в старых записях модуля ORIGIN_ID мог хранить сам source
        // ('cookie_banner'), а идентификация пользователя шла отдельно через USER_ID/IP.
        // Эти ветки нужны только для обратной совместимости со старыми consent-записями.
        if ($userId !== null && $rowOriginId === $source && $rowUserId === $userId) {
            return $row;
        }

        if ($userId === null && $rowOriginId === $source && $ip !== '' && $rowIp === $ip) {
            return $row;
        }
    }

    return null;
}

$response = function(array $data, int $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

// ---- ШИМ ДЛЯ КЛАССОВ (поддержка старого и нового пространств имён) ----
$ConsentClass       = class_exists('\Bitrix\Main\UserConsent\Consent')
    ? '\Bitrix\Main\UserConsent\Consent'
    : (class_exists('\Bitrix\UserConsent\Consent') ? '\Bitrix\UserConsent\Consent' : null);

$AgreementTableClass = class_exists('\Bitrix\Main\UserConsent\Internals\AgreementTable')
    ? '\Bitrix\Main\UserConsent\Internals\AgreementTable'
    : (class_exists('\Bitrix\UserConsent\Internals\AgreementTable') ? '\Bitrix\UserConsent\Internals\AgreementTable' : null);

$ConsentTableClass   = class_exists('\Bitrix\Main\UserConsent\Internals\ConsentTable')
    ? '\Bitrix\Main\UserConsent\Internals\ConsentTable'
    : (class_exists('\Bitrix\UserConsent\Internals\ConsentTable') ? '\Bitrix\UserConsent\Internals\ConsentTable' : null);

try {
    if (!$ConsentClass || !$AgreementTableClass || !$ConsentTableClass) {
        $response([
            'success' => false,
            'error'   => 'UserConsent API is not available in this core (no classes found)',
            'code'    => 'API_NOT_AVAILABLE'
        ], 500);
    }

    $context = Context::getCurrent();
    $request = $context->getRequest();

    if (!$request->isPost()) {
        $response(['success' => false, 'error' => 'Only POST is allowed'], 405);
    }
    if (function_exists('check_bitrix_sessid') && !check_bitrix_sessid()) {
        $response(['success' => false, 'error' => 'Bad sessid'], 403);
    }

    $siteId         = (string)($request->getPost('SITE_ID') ?: (defined('SITE_ID') ? SITE_ID : 's1'));
    $siteId         = preg_replace('/[^a-zA-Z0-9_]/', '', trim($siteId));
    if ($siteId === '') {
        $siteId = 's1';
    }
    $moduleId       = 'ushakov.cookie';
    $saveToRegistry = Option::get($moduleId, 'save_to_registry_'.$siteId, 'N');
    $agreementId    = (int)Option::get($moduleId, 'agreement_id_'.$siteId, 0);
    $oncePerSession = Option::get($moduleId, 'log_once_per_session_'.$siteId, 'N') === 'Y';

    if ($saveToRegistry !== 'Y' || $agreementId <= 0) {
        $response([
            'success' => true,
            'message' => 'Registry saving disabled (option off or no agreement ID)',
            'debug'   => compact('saveToRegistry','agreementId','siteId')
        ]);
    }

    // Проверяем что соглашение существует и активно
    /** @var \Bitrix\Main\ORM\Query\Result $agr */
    $agr = $AgreementTableClass::getList([
        'filter' => ['=ID' => $agreementId, '=ACTIVE' => 'Y'],
        'select' => ['ID','NAME','TYPE']
    ])->fetch();
    if (!$agr) {
        $response([
            'success' => false,
            'error'   => 'Agreement not found or inactive',
            'code'    => 'AGREEMENT_NOT_FOUND',
            'debug'   => ['agreementId' => $agreementId]
        ], 404);
    }

    global $USER, $APPLICATION;
    $userId = (is_object($USER) && $USER->IsAuthorized()) ? (int)$USER->GetID() : null;
    $ip     = $request->getRemoteAddress();
    $ua     = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $url    = (string)($request->getPost('url') ?: ($APPLICATION ? $APPLICATION->GetCurPageParam() : ($request->getRequestUri() ?: '')));
    $source = 'cookie_banner';
    $guestClientId = ushakovCookieNormalizeGuestClientId($request->getPost('GUEST_CLIENT_ID'));
    if ($guestClientId === '') {
        $guestClientId = ushakovCookieNormalizeGuestClientId($_COOKIE[ushakovCookieGetGuestCookieName($siteId)] ?? '');
    }
    $originId = ushakovCookieBuildOriginId($userId, $siteId, $guestClientId, (string) $ip);

    $text    = (string)$request->getPost('text');     // если показывать СВОЙ текст
    $options = $request->getPost('options');          // чекбоксы, если есть
    $originSessionFlagKey = ushakovCookieGetOriginSessionFlagKey($agreementId, $originId);

    ushakovCookieStartSessionIfNeeded();
    if (!empty($_SESSION[$originSessionFlagKey])) {
        $response(['success' => true, 'existing' => true, 'message' => 'Consent already logged in this session']);
    }

    if ($oncePerSession) {
        $flagKey = 'cookie_accept_logged_'.$agreementId;
        if (!empty($_SESSION[$flagKey])) {
            $response(['success'=>true,'skipped'=>true,'message'=>'Already logged in this session']);
        }
    }

    $existing = ushakovCookieFindExistingConsent($ConsentTableClass, $agreementId, $source, $originId, $userId, (string) $ip);

    if ($existing) {
        $_SESSION[$originSessionFlagKey] = true;
        if ($oncePerSession) { $_SESSION[$flagKey] = true; }
        $response([
            'success'   => true,
            'existing'  => true,
            'message'   => 'Consent already exists',
            'consentId' => (int)$existing['ID'],
        ]);
    }

    // Контекст для addByContext
    $ctx = [
        'USER_ID'           => $userId,
        'IP'                => $ip,
        'URL'               => $url,
        'USER_AGENT'        => $ua,
        'ORIGINATOR_ID'     => $source,
        'ORIGIN_ID'         => $originId,
        'ORIGINAL_TEXT'     => $text ?: null,
        'ORIGINAL_TEXT_HASH'=> $text ? hash('sha256', $text) : null,
        'OPTIONS_JSON'      => is_array($options) ? json_encode($options, JSON_UNESCAPED_UNICODE) : (is_string($options) ? $options : null),
    ];

    $result = $ConsentClass::addByContext($agreementId, $source, $originId, $ctx);

    $ok = false;
    $consentId = null;
    $errors = null;

    if (is_object($result)) {
        // Новые ядра: объект результата
        if (method_exists($result, 'isSuccess')) {
            $ok = (bool)$result->isSuccess();
        } else {
            // На всякий случай: если объект, но без isSuccess — считаем, что ок
            $ok = true;
        }
        if (method_exists($result, 'getId')) {
            $consentId = (int)$result->getId();
        }
        if (!$ok && method_exists($result, 'getErrorMessages')) {
            $errors = implode('; ', (array)$result->getErrorMessages());
        }
    } elseif (is_int($result)) {
        // Старые ядра: сразу ID
        $consentId = $result;
        $ok = ($consentId > 0);
    } elseif (is_bool($result)) {
        // Некоторые билды могут вернуть просто true/false
        $ok = $result;
    } else {
        // Неподдерживаемый тип
        $errors = 'Unexpected return type from addByContext: ' . gettype($result);
    }

    if (!$ok) {
        throw new SystemException($errors ?: 'Unknown error on addByContext');
    }

    // дописываем источник (на некоторых ядрах addByContext это «проглатывает»)
    $originUpdated = false;
    try {
        $ConsentTableClass::update($consentId, [
            'ORIGINATOR_ID' => $source,
            'ORIGIN_ID'     => $originId,
        ]);
        $originUpdated = true;
    } catch (Exception $e) {
        // Логируем ошибку, но не прерываем выполнение
        error_log('Failed to update ORIGIN fields: ' . $e->getMessage());
    }

    $_SESSION[$originSessionFlagKey] = true;
    if ($oncePerSession) { $_SESSION[$flagKey] = true; }

    $response([
        'success'   => true,
        'message'   => 'Consent saved successfully (API)',
        'consentId' => $consentId,
        'debug'     => [
            'agreement' => ['id'=>(int)$agr['ID'],'name'=>(string)$agr['NAME'],'type'=>(string)$agr['TYPE']],
            'ctx'       => [
                'USER_ID' => $userId,
                'IP' => $ip,
                'URL' => $url,
                'ORIGINATOR_ID' => $source,
                'ORIGIN_ID' => $originId,
                'GUEST_CLIENT_ID' => $guestClientId !== '' ? $guestClientId : null,
            ],
            'siteId'    => $siteId,
            'retType'   => is_object($result) ? 'object' : (is_int($result) ? 'int' : (is_bool($result) ? 'bool' : gettype($result))),
            'originUpdated' => $originUpdated,
            'source'    => $source,
        ]
    ]);

} catch (\Throwable $e) {
    $response([
        'success' => false,
        'error'   => $e->getMessage(),
    ], 500);
}
