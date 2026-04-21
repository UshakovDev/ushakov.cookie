<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$request = Application::getInstance()->getContext()->getRequest();
$siteId = (string) ($request->getPost('SITE_ID') ?: (defined('SITE_ID') ? SITE_ID : 's1'));
$siteId = preg_replace('/[^a-zA-Z0-9_]/', '', trim($siteId));
if ($siteId === '') {
    $siteId = 's1';
}

$disableMob = Option::get('ushakov.cookie', 'disableMob_' . $siteId, 'N');
$bgColor = Option::get('ushakov.cookie', 'bg_color_' . $siteId, 'rgba(0, 0, 0, 0.85)'); //цвет плашки
$borderRadius = Option::get('ushakov.cookie', 'border_radius_' . $siteId, '6px');
$shadow = Option::get('ushakov.cookie', 'shadow_' . $siteId, 'Y');

$position = Option::get('ushakov.cookie', 'position_' . $siteId, 'bottom');

$align = Option::get('ushakov.cookie', 'align_' . $siteId, 'center');
$align = in_array($align, ['left','center','right'], true) ? $align : 'center';

$maxWidth = Option::get('ushakov.cookie', 'max_width_' . $siteId, '640px');
$offsetX  = Option::get('ushakov.cookie', 'offset_x_'  . $siteId, '0px');
$offsetY  = Option::get('ushakov.cookie', 'offset_y_'  . $siteId, '7px');

$zIndex = Option::get('ushakov.cookie', 'z_index_' . $siteId, '9999');
$delayMs = \Bitrix\Main\Config\Option::get('ushakov.cookie', 'delay_ms', '0');
$delayMs = (is_numeric($delayMs) && (int)$delayMs >= 0) ? (int)$delayMs : 0;
$textButton = Option::get('ushakov.cookie', 'textButton_' . $siteId, '');

$acceptBtnPosition = Option::get('ushakov.cookie', 'accept_btn_position_' . $siteId, 'right');
$closeBtnPosition = Option::get('ushakov.cookie', 'close_btn_position_' . $siteId, 'right-top');
$acceptBtnBgColor = Option::get('ushakov.cookie', 'accept_btn_bg_color_' . $siteId, '#4CAF50');
$acceptBtnTextColor = Option::get('ushakov.cookie', 'accept_btn_text_color_' . $siteId, '#FFFFFF');
$closeBtnColor = Option::get('ushakov.cookie', 'close_btn_color_' . $siteId, 'rgb(255, 7, 7)');


// Замена текста в решётках на тег <a>
$link = Option::get('ushakov.cookie', 'link_' . $siteId);
if (trim($link) === '') {
    $link = '/cookies-agreement.php';
}
// $textTemplate = Option::get('ushakov.cookie', 'text_' . $siteId);
$textTemplate = Option::get('ushakov.cookie', 'text_' . $siteId, '');
if (trim($textTemplate) === '') {
    $textTemplate = "<div style='text-align: center;'>Мы используем файлы cookie для работы сайта и сбора статистики. Продолжая пользоваться сайтом, вы соглашаетесь с нашей <a href='/cookies-agreement.php' target='_blank'>Политикой использования cookie</a>.</div>";
}

$allowed = '<a><p><b><strong><i><em><u><br><div><span><font>'
         . '<ul><ol><li>'
         . '<h1><h2><h3><h4><h5><h6><blockquote>';
$text = trim(strip_tags($textTemplate, $allowed));

// простая валидация радиуса (разрешим px|rem|em|%)
$borderRadius = trim($borderRadius);
if (!preg_match('/^\d+(\.\d+)?(px|rem|em|%)$/i', $borderRadius)) {
    $borderRadius = '6px';
}

// нормализуем
$position = ($position === 'top') ? 'top' : 'bottom';
$unitRe = '/^\s*\d+(\.\d+)?(px|rem|em|%)\s*$/i';
$maxWidth = preg_match($unitRe, $maxWidth) ? trim($maxWidth) : '640px';
$offsetX  = preg_match($unitRe, $offsetX)  ? trim($offsetX)  : '0px';
$offsetY  = preg_match($unitRe, $offsetY)  ? trim($offsetY)  : '7px';

$responseData = [
    'status' => 'success',
    'message' => 'Cookie applied successfully',
    'data' => [
        'siteId' => $siteId,
        'disableMob' => in_array($disableMob, ['Y', 'N']) ? $disableMob : 'N',
        'text' => $text,
        'bgColor' => $bgColor, // цвет плашки
        'borderRadius' => $borderRadius,
        'shadow' => in_array($shadow, ['Y','N'], true) ? $shadow : 'Y',

        'position' => $position,

        'align' => $align,

        'maxWidth' => $maxWidth,
        'offsetX'  => $offsetX,
        'offsetY'  => $offsetY,

        'zIndex' => intval($zIndex) >= 0 ? intval($zIndex) : '9999',
        'delayMs' => $delayMs,
        'textButton' => $textButton ? : '',

        'acceptBtnPosition' => in_array($acceptBtnPosition, ['left', 'right', 'bottom']) ? $acceptBtnPosition : 'right',
        'closeBtnPosition' => in_array($closeBtnPosition, ['left-top','right-top','left-middle','right-middle'], true)
              ? $closeBtnPosition : 'right-top',
        'acceptBtnBgColor'  => $acceptBtnBgColor ?: '#4CAF50',
        'acceptBtnTextColor'=> $acceptBtnTextColor ?: '#FFFFFF',
        'closeBtnColor'     => $closeBtnColor ?: 'rgb(255, 7, 7)',
    ]
];

header('Content-Type: application/json');

if (!$json = json_encode($responseData)) {
    if (!mb_check_encoding($text, 'UTF-8')) {
        $responseData['data']['text'] = mb_convert_encoding($text, 'UTF-8', 'Windows-1251');
        $json = json_encode($responseData);
    }
}

echo $json;
