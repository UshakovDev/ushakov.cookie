<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;

$active = Option::get('ushakov.cookie', 'active_' . SITE_ID);

// Подключаем модуль только при явном включении для текущего сайта.
if ($active !== 'Y' || strpos($_SERVER['REQUEST_URI'], '/bitrix/admin') !== false) {
    return;
}

require_once __DIR__ . '/lib/analytics.php';

$ushakovCookieAnalyticsConfig = UshakovCookieAnalytics::buildRuntimeConfig(SITE_ID, $_COOKIE);

Asset::getInstance()->addString(
    '<script>window.ushakovCookieConfig = ' . \CUtil::PhpToJSObject([
        'siteId' => SITE_ID,
        'sessid' => bitrix_sessid(),
        'endpoints' => [
            'optionsUrl' => '/bitrix/tools/ushakov_cookie_options.php',
            'saveUrl' => '/bitrix/tools/ushakov_cookie_save.php',
            'consentUrl' => '/bitrix/tools/ushakov_cookie_consent.php',
            'debugUrl' => '/bitrix/tools/ushakov_cookie_debug.php',
        ],
        'analytics' => $ushakovCookieAnalyticsConfig,
    ]) . ';</script>'
);

// Server-side gating: snippet Метрики печатается только если managed mode включён,
// решение пользователя = 'accepted' и задан корректный ID счётчика.
$ushakovCookieMetrikaSnippet = UshakovCookieAnalytics::renderMetrikaSnippet($ushakovCookieAnalyticsConfig);
if ($ushakovCookieMetrikaSnippet !== '') {
    Asset::getInstance()->addString($ushakovCookieMetrikaSnippet);
}

Asset::getInstance()->addJs('/bitrix/js/ushakov.cookie/analytics.js');
Asset::getInstance()->addJs('/bitrix/js/ushakov.cookie/script.js');
Asset::getInstance()->addCss('/bitrix/css/ushakov.cookie/style.css');
