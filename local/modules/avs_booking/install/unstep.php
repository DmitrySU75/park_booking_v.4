<?php
if (!check_bitrix_sessid()) return;
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="avs_booking">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <p>Удаление модуля "Модуль бронирования AVS"</p>

    <p>
        <input type="checkbox" name="preserve_data" id="preserve_data" value="Y">
        <label for="preserve_data">Сохранить данные (инфоблоки, настройки)</label>
    </p>

    <input type="submit" name="inst" value="Удалить">
</form>