<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\SiteTable;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=UTF-8');

$response = static function (array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};

$request = Application::getInstance()->getContext()->getRequest();

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

$siteId = (string) ($request->getPost('SITE_ID') ?: (defined('SITE_ID') ? SITE_ID : 's1'));
$siteId = preg_replace('/[^a-zA-Z0-9_]/', '', trim($siteId));
if ($siteId === '') {
    $siteId = 's1';
}

$mode = Option::get('ushakov.cookie', 'consent_mode', 'days');
$days = (int) Option::get('ushakov.cookie', 'days', '365');
if ($days <= 0) {
    $days = 365;
}

$expires = ($mode === 'session') ? 0 : (time() + $days * 86400);
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$cookieName = 'ushakov_cookie_' . $siteId;
$cookiePath = '/';

$site = SiteTable::getList([
    'filter' => ['=LID' => $siteId],
    'select' => ['DIR'],
    'limit' => 1,
])->fetch();

if (!empty($site['DIR']) && is_string($site['DIR'])) {
    $cookiePath = '/' . ltrim($site['DIR'], '/');
    if (substr($cookiePath, -1) !== '/') {
        $cookiePath .= '/';
    }
}

setcookie($cookieName, '1', [
    'expires' => $expires,
    'path' => $cookiePath,
    'secure' => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);

$response([
    'success' => true,
    'siteId' => $siteId,
    'cookieName' => $cookieName,
    'cookiePath' => $cookiePath,
]);
