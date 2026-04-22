<?php

if (!check_bitrix_sessid()) {
    return;
}

global $obModule;
if (!is_object($obModule)) {
    return;
}

if (is_array($obModule->errors) && count($obModule->errors)) {
    CAdminMessage::ShowMessage(
        [
            "TYPE" => "ERROR",
            "MESSAGE" => "MOD_UNINST_ERR",
            "DETAILS" => implode("<br>", $obModule->errors),
            "HTML" => true
        ]
    );
} else {
    CAdminMessage::ShowNote(GetMessage("MOD_UNINST_OK"));
}
?>
<form action="<?= $APPLICATION->GetCurPage() ?>" method="get">
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="submit" value="<?= GetMessage("MOD_BACK") ?>">
</form>
