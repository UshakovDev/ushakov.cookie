<?php
/**
 * AJAX: collect managed Yandex Metrica diagnostic events.
 */
define('STOP_STATISTICS', true);
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Context;

header('Content-Type: application/json; charset=UTF-8');

$response = static function (array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$docRoot = rtrim((string) Application::getInstance()->getContext()->getServer()->getDocumentRoot(), '/');
$debugLogPath = $docRoot . '/local/modules/ushakov.cookie/lib/debuglog.php';
if (!is_file($debugLogPath)) {
    $debugLogPath = $docRoot . '/bitrix/modules/ushakov.cookie/lib/debuglog.php';
}

if (!is_file($debugLogPath)) {
    $response([
        'success' => false,
        'error' => 'Debug log helper is not available',
    ], 500);
}

require_once $debugLogPath;

$request = Context::getCurrent()->getRequest();

if (!$request->isPost()) {
    $response([
        'success' => false,
        'error' => 'Only POST is allowed',
    ], 405);
}

if (function_exists('check_bitrix_sessid') && !check_bitrix_sessid()) {
    $response([
        'success' => false,
        'error' => 'Bad sessid',
    ], 403);
}

$siteId = UshakovCookieDebugLog::normalizeSiteId($request->getPost('SITE_ID') ?: (defined('SITE_ID') ? SITE_ID : 's1'));
if (!UshakovCookieDebugLog::isEnabled($siteId)) {
    $response([
        'success' => true,
        'skipped' => true,
        'message' => 'Debug mode is disabled',
    ]);
}

$details = [];
$detailsRaw = (string) $request->getPost('details');
if ($detailsRaw !== '') {
    $decodedDetails = json_decode($detailsRaw, true);
    if (is_array($decodedDetails)) {
        $details = $decodedDetails;
    }
}

$written = UshakovCookieDebugLog::write(
    $siteId,
    (string) $request->getPost('level'),
    (string) $request->getPost('event'),
    (string) $request->getPost('message'),
    $details
);

$data = [
    'success' => $written,
    'siteId' => $siteId,
];

if (!$written) {
    $data['errorCode'] = UshakovCookieDebugLog::getLastErrorCode();
    $data['error'] = UshakovCookieDebugLog::getLastErrorMessage();
}

$response($data);
