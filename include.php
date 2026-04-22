<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Page\Asset;

$active = Option::get('ushakov.cookie', 'active_' . SITE_ID);

// Подключаем баннер только при явном включении для текущего сайта.
if ($active !== 'Y' || strpos($_SERVER['REQUEST_URI'], '/bitrix/admin') !== false) {
    return;
}

Asset::getInstance()->addString(
    '<script>window.ushakovCookieConfig = ' . \CUtil::PhpToJSObject([
        'siteId' => SITE_ID,
        'sessid' => bitrix_sessid(),
        'endpoints' => [
            'optionsUrl' => '/bitrix/tools/ushakov_cookie_options.php',
            'saveUrl' => '/bitrix/tools/ushakov_cookie_save.php',
            'consentUrl' => '/bitrix/tools/ushakov_cookie_consent.php',
        ],
    ]) . ';</script>'
);
Asset::getInstance()->addJs('/bitrix/js/ushakov.cookie/script.js');
Asset::getInstance()->addCss('/bitrix/css/ushakov.cookie/style.css');