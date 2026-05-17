<?php

/**
 * @global CUser $USER
 * @global CMain $APPLICATION
 * @global $Update
 * @global $Apply
 * @global $RestoreDefaults
 * @global $mid
 */

// Проверка прав
$modulePerms = $APPLICATION->GetGroupRight($mid);
if ($modulePerms < 'R') {
    return;
}

\Bitrix\Main\Localization\Loc::loadMessages($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/options.php');
\Bitrix\Main\Localization\Loc::loadMessages(__FILE__);

$debugLogPath = __DIR__ . '/lib/debuglog.php';
if (is_file($debugLogPath)) {
    require_once $debugLogPath;
}

// Получение конфигурации табов и опций
$tabsConf = require __DIR__ . '/options_conf.php';

// Создаём массив табов для создания экземпляра объекта
$arTabs = $arAllOptions = [];
foreach ($tabsConf as $key => $arTab) {
    $arTabs[] = ['DIV' => $key, 'TAB' => $arTab['TAB_NAME'], 'TITLE' => $arTab['TAB_TITLE'], 'ICON' => $arTab['ICON']];
    $arAllOptions = array_merge($arAllOptions, $arTab['options']);
}

// Объект табов
$tabControl = new CAdminTabControl('tabControl', $arTabs);

$getOptionValue = static function (string $moduleId, array $option) {
    if (!isset($option['name']) || $option['name'] === '') {
        return $option['value'] ?? '';
    }

    $storedValue = \Bitrix\Main\Config\Option::get($moduleId, $option['name'], null);

    return $storedValue === null ? ($option['value'] ?? '') : $storedValue;
};

// Сохранение значений
$request = \Bitrix\Main\Context::getCurrent()->getRequest();

if (
    $request->isPost() &&
    $modulePerms === 'W' &&
    (string) $request->getPost('ushakov_debug_clear_site') !== '' &&
    check_bitrix_sessid()
) {
    $clearSiteId = class_exists('UshakovCookieDebugLog')
        ? UshakovCookieDebugLog::normalizeSiteId($request->getPost('ushakov_debug_clear_site'))
        : preg_replace('/[^a-zA-Z0-9_]/', '', (string) $request->getPost('ushakov_debug_clear_site'));

    if ($clearSiteId !== '' && class_exists('UshakovCookieDebugLog')) {
        UshakovCookieDebugLog::clear($clearSiteId);
    }

    LocalRedirect(
        $APPLICATION->GetCurPage() .
        '?mid=' . urlencode($mid) .
        '&lang=' . urlencode(LANGUAGE_ID) .
        '&ushakov_debug_cleared=Y' .
        '&' . $tabControl->ActiveTabParam()
    );
}

if($request->isPost() && $Update.$Apply.$RestoreDefaults <> '' && $modulePerms === 'W' && check_bitrix_sessid())
{
    if (strlen($RestoreDefaults) > 0) {
        \Bitrix\Main\Config\Option::delete($mid);
    } else {
        foreach ($arAllOptions as $arOption) {
            if (
                empty($arOption['name']) ||
                in_array($arOption['type'] ?? '', ['heading', 'message', 'diagnostics'], true)
            ) {
                continue;
            }

            $name = $arOption['name'];
            $val = $request->get($name);
            if ($val === null) {
                continue;
            }
            if ($arOption['type'] === 'checkbox' && $val !== 'Y') {
                $val = 'N';
            }
            \Bitrix\Main\Config\Option::set($mid, $name, $val);
        }
    }
    if (strlen($Update) > 0 && strlen($request->get('back_url_settings')) > 0) {
        LocalRedirect($request->get('back_url_settings'));
    } else {
        LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($mid) . '&lang=' . urlencode(LANGUAGE_ID) . '&back_url_settings=' . urlencode($request->get('back_url_settings')) . '&' . $tabControl->ActiveTabParam());
    }
}

// Определяем названия групп
$groupNames = [
    'SITE_SETTINGS' => 'Настройки сайта',
    'CONTENT' => 'Содержимое и кнопки',
    'APPEARANCE' => 'Внешний вид',
    'POSITION' => 'Положение плашки',
    'BEHAVIOR' => 'Поведение и время',
    'INTEGRATION' => 'Интеграция с Bitrix',
    'ANALYTICS' => 'Управляемая Яндекс.Метрика',
];

$renderDebugDiagnostics = static function (string $siteId) use ($mid, $modulePerms): string {
    $siteId = class_exists('UshakovCookieDebugLog')
        ? UshakovCookieDebugLog::normalizeSiteId($siteId)
        : preg_replace('/[^a-zA-Z0-9_]/', '', $siteId);

    $managed = \Bitrix\Main\Config\Option::get($mid, 'ym_managed_' . $siteId, 'N') === 'Y';
    $debug = \Bitrix\Main\Config\Option::get($mid, 'ym_debug_' . $siteId, 'N') === 'Y';
    $counterRaw = trim((string) \Bitrix\Main\Config\Option::get($mid, 'ym_counter_id_' . $siteId, ''));
    $counterValid = $counterRaw !== '' && preg_match('/^\d+$/', $counterRaw) && (int) $counterRaw > 0;
    $entries = class_exists('UshakovCookieDebugLog') ? UshakovCookieDebugLog::read($siteId, 100) : [];
    $lines = [];

    foreach ($entries as $entry) {
        $lines[] = UshakovCookieDebugLog::formatEntry($entry);
    }

    $logText = $lines
        ? implode(PHP_EOL, $lines)
        : (string) \Bitrix\Main\Localization\Loc::getMessage('USHAKOV_COOKIE_OPT_YM_DIAGNOSTICS_EMPTY');

    $chip = static function (string $label, bool $ok, string $value): string {
        return '<span class="ushakov-cookie-diag-chip ' . ($ok ? 'is-ok' : 'is-muted') . '">' .
            htmlspecialcharsbx($label . ': ' . $value) .
            '</span>';
    };

    $clearDisabled = $modulePerms === 'W' ? '' : ' disabled';
    $clearButton = '<button type="submit" class="adm-btn" name="ushakov_debug_clear_site" value="' .
        htmlspecialcharsbx($siteId) . '"' . $clearDisabled . '>' .
        htmlspecialcharsbx((string) \Bitrix\Main\Localization\Loc::getMessage('USHAKOV_COOKIE_OPT_YM_DIAGNOSTICS_CLEAR')) .
        '</button>';

    return '<div class="ushakov-cookie-diagnostics">' .
        '<div class="ushakov-cookie-diag-status">' .
        $chip('Managed', $managed, $managed ? 'Y' : 'N') .
        $chip('Debug', $debug, $debug ? 'Y' : 'N') .
        $chip('Counter ID', $counterValid, $counterValid ? $counterRaw : 'not set') .
        $chip('Events', count($entries) > 0, (string) count($entries)) .
        '</div>' .
        '<textarea class="ushakov-cookie-diag-log" readonly rows="12">' . htmlspecialcharsbx($logText) . '</textarea>' .
        '<div class="ushakov-cookie-diag-actions">' .
        $clearButton .
        '<span class="ushakov-cookie-diag-hint">' .
        htmlspecialcharsbx((string) \Bitrix\Main\Localization\Loc::getMessage('USHAKOV_COOKIE_OPT_YM_DIAGNOSTICS_HINT')) .
        '</span>' .
        '</div>' .
        '</div>';
};

if ($request->get('ushakov_debug_cleared') === 'Y') {
    CAdminMessage::ShowNote(\Bitrix\Main\Localization\Loc::getMessage('USHAKOV_COOKIE_OPT_YM_DIAGNOSTICS_CLEARED'));
}

/*
 * Вывод интерфейса опций
 */
$tabControl->Begin();
?>
<form method="post" action="<?=$APPLICATION->GetCurPage()?>?mid=<?=urlencode($mid)?>&lang=<?=LANGUAGE_ID?>">
    <?php
    foreach ($tabsConf as $arTab) {
        if (!array_key_exists('options', $arTab) || !is_array($arTab['options'])) {
            continue;
        }
        $tabControl->BeginNextTab();
        
        // Группируем опции по группам
        $groupedOptions = [];
        foreach ($arTab['options'] as $arOption) {
            $group = $arOption['group'] ?? 'OTHER';
            if (!isset($groupedOptions[$group])) {
                $groupedOptions[$group] = [];
            }
            $groupedOptions[$group][] = $arOption;
        }
        
        // Выводим опции по группам
        foreach ($groupedOptions as $groupKey => $groupOptions) {
            // Выводим заголовок группы
            if (isset($groupNames[$groupKey])) {
                ?><tr class="heading group-header" data-group="<?=$groupKey?>"><td colspan="2"><?=$groupNames[$groupKey]?></td></tr><?php
            }
            
            // Выводим опции группы
            foreach ($groupOptions as $arOption) {
                if ($arOption['type'] === 'heading') {
                    ?><tr class="heading"><td colspan="2"><?=$arOption['heading']?></td></tr><?php
                } elseif ($arOption['type'] === 'message') {
                    ?><tr><td colspan="2" align="center"><div class="adm-info-message-wrap" align="center"><div class="adm-info-message"><?=$arOption['message']?></div></div></td></tr><?php
                } elseif ($arOption['type'] === 'diagnostics') {
                    ?><tr><td colspan="2"><?=$renderDebugDiagnostics((string) ($arOption['siteId'] ?? 's1'))?></td></tr><?php
                } else {
                    $val = $getOptionValue($mid, $arOption);
                    ?>
                    <tr>
                        <td width="50%" class="adm-detail-content-cell-l" nowrap<?= $arOption['type'] === 'textarea' ? ' class="adm-detail-valign-top"' : '' ?>>
                            <label for="<?= $arOption['name']; ?>"><?= $arOption['title']; ?>:</label>
                        <td width="50%" class="adm-detail-content-cell-r">
                            <?php
                            switch ($arOption['type'])
                            {
                                case 'checkbox':
                                    ?><input type="hidden" name="<?= $arOption['name']; ?>" value="N">
                                    <input type="checkbox" id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" value="Y"<?= ($val === 'Y' ? ' checked' : ''); ?>><?php
                                    break;
                                case 'text':
                                    ?><input type="text" id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" value="<?= htmlspecialcharsbx($val); ?>" size="<?=$arOption['size']?:44?>" maxlength="255" placeholder="<?= $arOption['placeholder'] ?? ''?>"><?php
                                    break;
                                case 'textarea':
                                    ?><textarea id="<?= $arOption['name']; ?>" name="<?= $arOption['name']; ?>" cols="<?=$arOption['cols']?:43?>" rows="<?=$arOption['rows']?:4?>"><?= htmlspecialcharsbx($val); ?></textarea><?php
                                    break;
                                case 'list':
                                    ?>
                                    <select id="<?=$arOption['name']?>" name="<?=$arOption['name']?>">
                                        <?php foreach ($arOption['list'] as $listValue => $listTitle):?>
                                        <option value="<?=$listValue?>"<?=$listValue==$val?' selected':''?>><?=$listTitle?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php
                                    break;
                                case 'custom':
                                    echo $arOption['html'] ?? '';
                                    break;
                            }
                            ?>
                        </td>
                    </tr>
                    <?php
                }
            }
        }
        $tabControl->EndTab();
    }
    ?>
    <?php $tabControl->Buttons();?>
    <input type="submit" name="Update" <?= $modulePerms < 'W' ? 'disabled' : '' ?> value="<?=GetMessage('MAIN_SAVE')?>" title="<?=GetMessage('MAIN_OPT_SAVE_TITLE')?>" class="adm-btn-save">
    <input type="submit" name="Apply" value="<?=GetMessage('MAIN_OPT_APPLY')?>" title="<?=GetMessage('MAIN_OPT_APPLY_TITLE')?>">
    <input type="submit" name="RestoreDefaults" <?= $modulePerms < 'W' ? 'disabled' : '' ?> title="<?=AddSlashes(GetMessage('MAIN_HINT_RESTORE_DEFAULTS'))?>" OnClick="return confirm('<?=AddSlashes(GetMessage('MAIN_HINT_RESTORE_DEFAULTS_WARNING'))?>')" value="<?=GetMessage('MAIN_RESTORE_DEFAULTS')?>">
    <?=bitrix_sessid_post();?>
    <?php $tabControl->End();?>

    <!-- Spectrum color picker -->
    <?php
    $APPLICATION->AddHeadScript('/bitrix/js/ushakov.cookie/jquery-3.6.0.min.js');
    $APPLICATION->SetAdditionalCSS('/bitrix/css/ushakov.cookie/spectrum.min.css');
    $APPLICATION->AddHeadScript('/bitrix/js/ushakov.cookie/spectrum.min.js');
    ?>
    <script>
    BX.ready(function () {
        const inputs = document.querySelectorAll('.spectrum-bg-color');
        inputs.forEach(function(input) {
        if (typeof $(input).spectrum === 'function') {
            $(input).spectrum({
            type: "component",
            showAlpha: true,
            preferredFormat: "rgb",
            allowEmpty: false,
            showInput: true
            });
        }
        });
    });
    </script>

    <script>
    BX.ready(function() {
        var mode = document.querySelector('select[name="consent_mode"]');
        var daysInput = document.querySelector('input[name="days"]');
    if (!mode || !daysInput) return;

    var daysRow = daysInput.closest('tr') || daysInput.parentElement.closest('tr');

    function toggleDays() {
        if (!daysRow) return;
        daysRow.style.display = (mode.value === 'session') ? 'none' : '';
    }

    mode.addEventListener('change', toggleDays);
    toggleDays(); // первичная установка при загрузке
    });
    </script>

    <script>
    BX.ready(function () {
        var input = document.getElementById('delay_ms');
        var hint  = document.getElementById('delay_ms_hint');
    if (!input || !hint) return;

    function updateHint() {
        var v = parseInt(input.value, 10);
        if (!isNaN(v) && v > 0) {
        hint.textContent = '≈ ' + (v / 1000).toFixed(2) + ' сек';
        } else {
        hint.textContent = '';
        }
    }
    input.addEventListener('input', updateHint);
    updateHint();
    });
    </script>

</form>
<style>
    .adm-detail-content-table > tbody > .heading td {
        padding: 7px 70px 9px!important;
    }
    .adm-workarea .heading td {
        background-color: #e9f0f2;
    }
    .adm-detail-content-cell-l {
        user-select: none
    }
    
    /* Стили для групп настроек */
    .adm-detail-content-table > tbody > tr.group-header td {
        background-color: #f0f8ff !important;
        border-bottom: 2px solid #4a90e2;
        font-weight: bold;
        color: #2c5aa0;
        padding: 10px 70px 8px !important;
    }
    
    .adm-detail-content-table > tbody > tr.group-header td:before {
        content: "📋 ";
        margin-right: 8px;
    }
    
    /* Отступы между группами */
    .adm-detail-content-table > tbody > tr:not(.group-header) + tr.group-header {
        margin-top: 20px;
    }
    
    /* Стили для разных групп */
    .adm-detail-content-table > tbody > tr.group-header[data-group="SITE_SETTINGS"] td:before {
        content: "🌐 ";
    }
    
    .adm-detail-content-table > tbody > tr.group-header[data-group="CONTENT"] td:before {
        content: "📝 ";
    }
    
    .adm-detail-content-table > tbody > tr.group-header[data-group="APPEARANCE"] td:before {
        content: "🎨 ";
    }
    
    .adm-detail-content-table > tbody > tr.group-header[data-group="POSITION"] td:before {
        content: "📍 ";
    }
    
    .adm-detail-content-table > tbody > tr.group-header[data-group="BEHAVIOR"] td:before {
        content: "⚙️ ";
    }
    
    .adm-detail-content-table > tbody > tr.group-header[data-group="INTEGRATION"] td:before {
        content: "🔗 ";
    }

    .adm-detail-content-table > tbody > tr.group-header[data-group="ANALYTICS"] td:before {
        content: "📊 ";
    }

    .ushakov-cookie-diagnostics {
        box-sizing: border-box;
        width: 100%;
        padding: 12px;
        border: 1px solid #dce3e8;
        border-radius: 4px;
        background: #fbfdff;
    }

    .ushakov-cookie-diag-status {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
    }

    .ushakov-cookie-diag-chip {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 3px;
        background: #edf3f8;
        color: #42515c;
        font-size: 12px;
        line-height: 16px;
    }

    .ushakov-cookie-diag-chip.is-ok {
        background: #e6f4ea;
        color: #166534;
    }

    .ushakov-cookie-diag-chip.is-muted {
        background: #f1f3f5;
        color: #6b7785;
    }

    .ushakov-cookie-diag-log {
        box-sizing: border-box;
        width: 100%;
        min-height: 180px;
        resize: vertical;
        padding: 10px;
        border: 1px solid #cfd9df;
        border-radius: 4px;
        background: #ffffff;
        color: #20262d;
        font: 12px/1.5 Consolas, Monaco, monospace;
        white-space: pre;
    }

    .ushakov-cookie-diag-actions {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 10px;
    }

    .ushakov-cookie-diag-hint {
        color: #6b7785;
        font-size: 12px;
        line-height: 16px;
    }
</style>

<script>
BX.ready(function () {
    document.querySelectorAll('input[type="checkbox"][name^="ym_managed_"]').forEach(function (toggle) {
        var siteId = toggle.name.substr('ym_managed_'.length);
        var dependentNames = [
            'ym_counter_id_' + siteId,
            'ym_webvisor_' + siteId,
            'ym_clickmap_' + siteId,
            'ym_track_links_' + siteId,
            'ym_accurate_track_bounce_' + siteId,
            'ym_ecommerce_' + siteId,
            'ym_ecommerce_container_' + siteId,
            'ym_debug_' + siteId,
        ];
        var rows = dependentNames
            .map(function (name) {
                var input = document.querySelector('[name="' + name + '"]:not([type="hidden"])');
                return input ? input.closest('tr') : null;
            })
            .filter(Boolean);

        function applyState() {
            var on = !!toggle.checked;
            rows.forEach(function (row) { row.style.display = on ? '' : 'none'; });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    });
});
</script>
