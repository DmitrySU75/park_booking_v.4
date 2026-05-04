<?php
if (!check_bitrix_sessid()) return;
?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="avs_booking">
    <input type="hidden" name="install" value="Y">
    <input type="hidden" name="step" value="2">

    <p>Модуль успешно установлен!</p>

    <p>Далее необходимо:</p>
    <ul>
        <li>Перейти в <a href="/bitrix/admin/settings.php?mid=avs_booking&lang=<?= LANG ?>">настройки модуля</a> и заполнить параметры API</li>
        <li>Добавить вызов компонента в шаблон детальной страницы беседки</li>
        <li>Настроить платежную систему ЮKassa в разделе "Магазин → Платежные системы"</li>
    </ul>

    <input type="submit" value="Перейти к настройкам">
</form>