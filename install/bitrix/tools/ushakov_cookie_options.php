<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

function ushakovCookieIsAllowedHref(string $href): bool
{
    return (bool) preg_match('~^(https?://|mailto:|tel:|/(?!/)|\#|\./|\.\./)~iu', $href);
}

function ushakovCookieNormalizeLinks(string $html): string
{
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $internalErrors = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML(
        '<?xml encoding="utf-8" ?><div id="ushakov-cookie-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($internalErrors);

    if (!$loaded) {
        return $html;
    }

    $root = $dom->getElementById('ushakov-cookie-root');
    if (!$root) {
        return $html;
    }

    $links = [];
    foreach ($root->getElementsByTagName('a') as $link) {
        $links[] = $link;
    }

    foreach ($links as $link) {
        $href = trim((string) $link->getAttribute('href'));
        if ($href === '' || !ushakovCookieIsAllowedHref($href)) {
            $link->removeAttribute('href');
        }

        $target = strtolower(trim((string) $link->getAttribute('target')));
        if ($target === '_blank') {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer nofollow');
        } elseif (in_array($target, ['_self', '_parent', '_top'], true)) {
            $link->setAttribute('target', $target);
            $link->removeAttribute('rel');
        } else {
            $link->removeAttribute('target');
            $link->removeAttribute('rel');
        }
    }

    $result = '';
    foreach ($root->childNodes as $childNode) {
        $result .= $dom->saveHTML($childNode);
    }

    return trim($result);
}

function ushakovCookieSanitizeBannerHtml(string $html): string
{
    $sanitizer = new \CBXSanitizer();
    $sanitizer->setLevel(\CBXSanitizer::SECURE_LEVEL_MIDDLE);
    $sanitizer->AddTags([
        'div' => ['align'],
        'span' => [],
        'em' => [],
        'u' => [],
        'font' => ['color', 'size'],
        'p' => ['align'],
        'blockquote' => ['title', 'align'],
        'h1' => ['align'],
        'h2' => ['align'],
        'h3' => ['align'],
        'h4' => ['align'],
        'h5' => ['align'],
        'h6' => ['align'],
    ]);
    $sanitizer->allowAttributes([
        'target' => [
            'tag' => static function ($tag) {
                return $tag === 'a';
            },
            'content' => static function ($value) {
                return in_array(strtolower(trim((string) $value)), ['_blank', '_self', '_parent', '_top'], true);
            },
        ],
        'rel' => [
            'tag' => static function ($tag) {
                return $tag === 'a';
            },
            'content' => static function ($value) {
                return (bool) preg_match('/^[a-z\s-]+$/i', (string) $value);
            },
        ],
        'style' => [
            'tag' => static function ($tag) {
                return in_array($tag, ['div', 'p', 'span', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true);
            },
            'content' => static function ($value) {
                return (bool) preg_match('/^\s*text-align\s*:\s*(left|center|right|justify)\s*;?\s*$/i', (string) $value);
            },
        ],
    ]);

    $sanitized = trim($sanitizer->sanitizeHtml($html));

    return ushakovCookieNormalizeLinks($sanitized);
}

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

$textTemplate = Option::get('ushakov.cookie', 'text_' . $siteId, '');
if (trim($textTemplate) === '') {
    $textTemplate = "<div style='text-align: center;'>Мы используем файлы cookie для работы сайта и сбора статистики. Продолжая пользоваться сайтом, вы соглашаетесь с нашей <a href='/cookies-agreement.php' target='_blank'>Политикой использования cookie</a>.</div>";
}
$text = ushakovCookieSanitizeBannerHtml($textTemplate);

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
