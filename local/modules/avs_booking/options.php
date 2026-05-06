<?php
$module_id = 'avs_booking';
$RIGHTS = $APPLICATION->GetGroupRight($module_id);

if ($RIGHTS >= 'W') {
    $arAllOptions = [
        ['api_url', 'URL API LibreBooking', '', ['text', 100]],
        ['api_username', 'Логин API', '', ['text', 50]],
        ['api_password', 'Пароль API', '', ['password', 50]],
        ['api_key', 'API ключ для внешних запросов', '', ['text', 50]],
        ['api_allowed_ips', 'Разрешенные IP для API (через запятую)', '', ['text', 255]],
        ['beton_systems_shop_id', 'Shop ID (ЮKassa) - Бетонные Системы', '', ['text', 50]],
        ['beton_systems_secret_key', 'Secret key (ЮKassa) - Бетонные Системы', '', ['password', 50]],
        ['park_victory_shop_id', 'Shop ID (ЮKassa) - Парк Победы', '', ['text', 50]],
        ['park_victory_secret_key', 'Secret key (ЮKassa) - Парк Победы', '', ['password', 50]],
        ['b24_webhook_url', 'Webhook Битрикс24', '', ['text', 255]],
        ['admin_email', 'Email администратора', '', ['text', 100]],
        ['summer_season_start', 'Начало летнего сезона (дд.мм)', '', ['text', 10]],
        ['summer_season_end', 'Конец летнего сезона (дд.мм)', '', ['text', 10]],
        ['summer_end_hour', 'Час окончания работы летом', '', ['text', 5]],
        ['winter_end_hour', 'Час окончания работы зимой', '', ['text', 5]],
        ['price_periods_iblock_id', 'ID инфоблока ценовых периодов', '', ['text', 10]]
    ];

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
        foreach ($arAllOptions as $option) {
            $name = $option[0];
            $value = $_REQUEST[$name];
            if ($option[3][0] == 'checkbox') {
                $value = ($value == 'Y') ? 'Y' : 'N';
            }
            \Bitrix\Main\Config\Option::set($module_id, $name, $value);
        }
    }

    $tabControl = new CAdminTabControl('tabControl', [
        ['DIV' => 'edit1', 'TAB' => 'Основные настройки', 'TITLE' => 'Основные настройки модуля']
    ]);

?>
    <form method="post" action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($module_id) ?>&lang=<? echo LANGUAGE_ID ?>">
        <?= bitrix_sessid_post() ?>
        <?
        $tabControl->Begin();
        $tabControl->BeginNextTab();
        foreach ($arAllOptions as $Option) {
            $name = $Option[0];
            $val = \Bitrix\Main\Config\Option::get($module_id, $name);
            $type = $Option[3];
        ?>
        <tr>
            <td width="40%"><?= $Option[1] ?>:</td>
            <td width="60%">
                <input type="<?= $type[0] ?>" name="<?= $name ?>" value="<?= htmlspecialcharsbx($val) ?>" size="<?= $type[1] ?>">
            </td>
        </tr>
    <?
        }
        $tabControl->Buttons();
    ?>
    <input type="submit" name="save" value="Сохранить">
    <?
    $tabControl->End();
    ?>
    </form>
    <?php
}
    ?>