<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;

Loc::loadMessages(dirname(__FILE__) . '/options.php');

$buildSiteOptions = static function (array $site): array {
    $siteId = $site['LID'];
    $siteOptions = [];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'active_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACTIVE'),
        'value' => 'N',
        'group' => 'SITE_SETTINGS'
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'disableMob_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACTIVE_MOB'),
        'value' => 'N',
        'group' => 'SITE_SETTINGS'
    ];

    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'text_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_TEXT_LABEL'),
        'html'  => (function () use ($siteId) {
            $name = 'text_' . $siteId;
            $default = Loc::getMessage('USHAKOV_COOKIE_OPT_TEXT');
            $content = \Bitrix\Main\Config\Option::get('ushakov.cookie', $name, $default);

            if (\Bitrix\Main\Loader::includeModule('fileman')) {
                ob_start();
                $editor = new \CHTMLEditor();
                $editor->Show([
                    'id' => $name,
                    'inputName' => $name,
                    'content' => $content,
                    'siteId' => $siteId,
                    'width' => '100%',
                    'height' => 220,
                    'autoResize' => true,
                    'autoResizeOffset' => 40,
                    'bbCode' => false,
                    'useFileDialogs' => false,
                    'askBeforeUnloadPage' => false,
                    'showNodeNavi' => false,
                    'showTaskbars' => false,
                    'SeoUniqText' => false,
                    'controlsMap' => [
                        ['id' => 'ChangeView', 'compact' => true],
                        ['id' => 'StyleSelector', 'compact' => true],
                        ['id' => 'FontSelector', 'compact' => true],
                        ['id' => 'FontSize', 'compact' => true],
                        ['id' => 'Bold', 'compact' => true],
                        ['id' => 'Italic', 'compact' => true],
                        ['id' => 'Underline', 'compact' => true],
                        ['id' => 'AlignList', 'compact' => true],
                        ['id' => 'InsertLink', 'compact' => true],
                        ['id' => 'Color', 'compact' => true],
                        ['id' => 'Undo', 'compact' => true],
                        ['id' => 'Redo', 'compact' => true],
                        ['id' => 'OrderedList', 'compact' => true],
                        ['id' => 'UnorderedList', 'compact' => true],
                        ['id' => 'IndentButton', 'compact' => true],
                        ['id' => 'OutdentButton', 'compact' => true],
                        ['id' => 'InsertChar', 'compact' => true],
                        ['id' => 'Fullscreen', 'compact' => true],
                    ],
                ]);
                return ob_get_clean();
            }

            return '<textarea name="' . htmlspecialcharsbx($name) . '" cols="43" rows="4">' .
                htmlspecialcharsbx($content) .
                '</textarea>';
        })(),
        'group' => 'CONTENT'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'textButton_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_TEXT_BUTTON_LABEL'),
        'value' => '',
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_TEXT_BUTTON_PLACEHOLDER'),
        'group' => 'CONTENT'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'reject_text_button_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_REJECT_TEXT_BUTTON_LABEL'),
        'value' => '',
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_REJECT_TEXT_BUTTON_PLACEHOLDER'),
        'group' => 'CONTENT'
    ];

    $siteOptions[] = [
        'type'  => 'list',
        'name'  => 'accept_btn_position_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_POSITION'),
        'list'  => [
            'left'   => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_POSITION_LEFT'),
            'right'  => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_POSITION_RIGHT'),
            'bottom' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_POSITION_BOTTOM'),
        ],
        'value' => 'right',
        'group' => 'CONTENT'
    ];

    $acceptBg = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'accept_btn_bg_color_' . $siteId, '#4CAF50'));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'accept_btn_bg_color_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_BG_COLOR'),
        'html'  => '<input class="spectrum-bg-color" type="text" name="accept_btn_bg_color_' . $siteId . '" value="' . $acceptBg . '" style="width:140px;">',
        'group' => 'APPEARANCE'
    ];

    $acceptText = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'accept_btn_text_color_' . $siteId, '#FFFFFF'));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'accept_btn_text_color_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ACCEPT_BTN_TEXT_COLOR'),
        'html'  => '<input class="spectrum-bg-color" type="text" name="accept_btn_text_color_' . $siteId . '" value="' . $acceptText . '" style="width:140px;">',
        'group' => 'APPEARANCE'
    ];

    $rejectBg = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'reject_btn_bg_color_' . $siteId, 'transparent'));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'reject_btn_bg_color_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_REJECT_BTN_BG_COLOR'),
        'html'  => '<input class="spectrum-bg-color" type="text" name="reject_btn_bg_color_' . $siteId . '" value="' . $rejectBg . '" style="width:140px;">',
        'group' => 'APPEARANCE'
    ];

    $rejectText = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'reject_btn_text_color_' . $siteId, '#FFFFFF'));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'reject_btn_text_color_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_REJECT_BTN_TEXT_COLOR'),
        'html'  => '<input class="spectrum-bg-color" type="text" name="reject_btn_text_color_' . $siteId . '" value="' . $rejectText . '" style="width:140px;">',
        'group' => 'APPEARANCE'
    ];

    $saveToRegistry = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'save_to_registry_' . $siteId, 'N'));
    $siteOptions[] = [
        'type'  => 'list',
        'name'  => 'save_to_registry_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_SAVE_TO_REGISTRY'),
        'list'  => [
            'Y' => Loc::getMessage('USHAKOV_COOKIE_OPT_SAVE_TO_REGISTRY_Y'),
            'N' => Loc::getMessage('USHAKOV_COOKIE_OPT_SAVE_TO_REGISTRY_N'),
        ],
        'value' => $saveToRegistry,
        'group' => 'INTEGRATION'
    ];

    $agreementId = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'agreement_id_' . $siteId, ''));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'agreement_id_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_AGREEMENT_ID'),
        'html'  => '<input type="text" name="agreement_id_' . $siteId . '" value="' . $agreementId . '" placeholder="' . Loc::getMessage('USHAKOV_COOKIE_OPT_AGREEMENT_ID_PLACEHOLDER') . '" style="width:140px;"> <a href="/bitrix/admin/agreement_admin.php" target="_blank" style="margin-left: 10px; color: #0066cc; text-decoration: underline;">' . Loc::getMessage('USHAKOV_COOKIE_OPT_VIEW_AGREEMENTS_LINK') . '</a>',
        'group' => 'INTEGRATION'
    ];

    $bgColor = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'bg_color_' . $siteId, 'rgba(0, 0, 0, 0.85)'));
    $siteOptions[] = [
        'type'  => 'custom',
        'name'  => 'bg_color_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_BG_COLOR'),
        'html'  => '<input class="spectrum-bg-color" type="text" name="bg_color_' . $siteId . '" value="' . $bgColor . '" style="width:140px;">',
        'group' => 'APPEARANCE'
    ];

    $borderRadius = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'border_radius_' . $siteId, '6px'));
    $siteOptions[] = [
        'type' => 'text',
        'name' => 'border_radius_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_BORDER_RADIUS'),
        'value' => $borderRadius,
        'size' => 8,
        'placeholder' => '6px',
        'group' => 'APPEARANCE'
    ];

    $shadow = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'shadow_' . $siteId, 'Y'));
    $siteOptions[] = [
        'type'  => 'list',
        'name'  => 'shadow_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_SHADOW'),
        'list'  => [
            'Y' => Loc::getMessage('USHAKOV_COOKIE_OPT_SHADOW_Y'),
            'N' => Loc::getMessage('USHAKOV_COOKIE_OPT_SHADOW_N'),
        ],
        'value' => $shadow,
        'group' => 'APPEARANCE'
    ];

    $position = htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'position_' . $siteId, 'bottom'));
    $siteOptions[] = [
        'type'  => 'list',
        'name'  => 'position_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_POSITION'),
        'list'  => [
            'top'    => Loc::getMessage('USHAKOV_COOKIE_OPT_POSITION_TOP'),
            'bottom' => Loc::getMessage('USHAKOV_COOKIE_OPT_POSITION_BOTTOM'),
        ],
        'value' => $position,
        'group' => 'POSITION'
    ];

    $siteOptions[] = [
        'type'  => 'list',
        'name'  => 'align_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ALIGN'),
        'list'  => [
            'left'   => Loc::getMessage('USHAKOV_COOKIE_OPT_ALIGN_LEFT'),
            'center' => Loc::getMessage('USHAKOV_COOKIE_OPT_ALIGN_CENTER'),
            'right'  => Loc::getMessage('USHAKOV_COOKIE_OPT_ALIGN_RIGHT'),
        ],
        'value' => 'center',
        'group' => 'POSITION'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'max_width_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_MAX_WIDTH'),
        'value' => '640px',
        'size' => 8,
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_OPT_MAX_WIDTH_PLACEHOLDER'),
        'group' => 'POSITION'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'offset_x_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_OFFSET_X'),
        'value' => '0px',
        'size' => 6,
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_OPT_OFFSET_X_PLACEHOLDER'),
        'group' => 'POSITION'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'offset_y_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_OFFSET_Y'),
        'value' => '7px',
        'size' => 6,
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_OPT_OFFSET_Y_PLACEHOLDER'),
        'group' => 'POSITION'
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'z_index_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_ZINDEX'),
        'value' => '9999',
        'size' => 5,
        'group' => 'POSITION'
    ];

    // ===== Managed Yandex Metrica (P2.2 v1) =====
    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_managed_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_MANAGED'),
        'value' => 'N',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'ym_counter_id_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_COUNTER_ID'),
        'value' => '',
        'size' => 14,
        'placeholder' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_COUNTER_ID_PLACEHOLDER'),
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_webvisor_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_WEBVISOR'),
        'value' => 'Y',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_clickmap_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_CLICKMAP'),
        'value' => 'Y',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_track_links_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_TRACK_LINKS'),
        'value' => 'Y',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_accurate_track_bounce_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_ACCURATE_TRACK_BOUNCE'),
        'value' => 'Y',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_ecommerce_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_ECOMMERCE'),
        'value' => 'N',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'text',
        'name' => 'ym_ecommerce_container_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_ECOMMERCE_CONTAINER'),
        'value' => 'dataLayer',
        'size' => 14,
        'placeholder' => 'dataLayer',
        'group' => 'ANALYTICS',
    ];

    $siteOptions[] = [
        'type' => 'checkbox',
        'name' => 'ym_debug_' . $siteId,
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_YM_DEBUG'),
        'value' => 'N',
        'group' => 'ANALYTICS',
    ];

    return $siteOptions;
};

$commonOptions = [
    [
        'type' => 'heading',
        'heading' => Loc::getMessage('USHAKOV_COOKIE_OPT_COMMON'),
        'group' => 'BEHAVIOR'
    ],
    [
        'type'  => 'list',
        'name'  => 'consent_mode',
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_CONSENT_MODE'),
        'list'  => [
            'days'    => Loc::getMessage('USHAKOV_COOKIE_OPT_CONSENT_MODE_DAYS'),
            'session' => Loc::getMessage('USHAKOV_COOKIE_OPT_CONSENT_MODE_SESSION'),
        ],
        'value' => 'days',
        'group' => 'BEHAVIOR'
    ],
    [
        'type' => 'text',
        'name' => 'days',
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_DAYS'),
        'value' => '365',
        'size' => '10',
        'group' => 'BEHAVIOR'
    ],
    [
        'type'  => 'custom',
        'name'  => 'delay_ms',
        'title' => Loc::getMessage('USHAKOV_COOKIE_OPT_DELAY_MS'),
        'html'  => '<input type="number" id="delay_ms" name="delay_ms" value="' .
            htmlspecialcharsbx(\Bitrix\Main\Config\Option::get('ushakov.cookie', 'delay_ms', '0')) .
            '" min="0" step="100" style="width: 120px;">' .
            '<span style="margin-left:8px;color:#80868b;">мс (1000 мс = 1 сек.)</span>' .
            '<span id="delay_ms_hint" style="margin-left:12px;color:#80868b;"></span>',
        'group' => 'BEHAVIOR'
    ],
    [
        'type' => 'message',
        'message' => Loc::getMessage('USHAKOV_COOKIE_OPT_HELP_MESSAGE'),
        'group' => 'BEHAVIOR'
    ],
];

$tabs = [
    'common' => [
        'TAB_NAME' => Loc::getMessage('USHAKOV_COOKIE_OPT_COMMON'),
        'TAB_TITLE' => Loc::getMessage('USHAKOV_COOKIE_OPT_COMMON'),
        'ICON' => '',
        'options' => $commonOptions,
    ],
];

$res = SiteTable::getList(['order' => ['SORT' => 'ASC', 'LID' => 'ASC']]);
while ($item = $res->fetch()) {
    $tabId = 'site_' . $item['LID'];
    $siteLabel = '[' . $item['LID'] . '] ' . $item['NAME'];

    $tabs[$tabId] = [
        'TAB_NAME' => $siteLabel,
        'TAB_TITLE' => 'Настройки сайта ' . $siteLabel,
        'ICON' => '',
        'options' => $buildSiteOptions($item),
    ];
}

return $tabs;
