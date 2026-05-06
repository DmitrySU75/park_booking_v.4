<?php
$module_id = 'avs_booking';
$RIGHTS = $APPLICATION->GetGroupRight($module_id);

if ($RIGHTS >= 'W') {
    $arAllOptions = [
        ['api_key', 'API ключ для внешних запросов', '', ['text', 50]],
        ['api_allowed_ips', 'Разрешенные IP для API (через запятую)', '', ['text', 255]],
        ['beton_systems_shop_id', 'Shop ID (ЮKassa) - Бетонные Системы', '', ['text', 50]],
        ['beton_systems_secret_key', 'Secret key (ЮKassa) - Бетонные Системы', '', ['password', 50]],
        ['park_victory_shop_id', 'Shop ID (ЮKassa) - Парк Победы', '', ['text', 50]],
        ['park_victory_secret_key', 'Secret key (ЮKassa) - Парк Победы', '', ['password', 50]],
        ['admin_email', 'Email администратора', '', ['text', 100]],
        ['manager_email', 'Email менеджера', '', ['text', 100]],
        ['b24_webhook_url', 'Webhook Битрикс24', '', ['text', 255]],
        ['tg_bot_token', 'Telegram Bot Token', '', ['text', 100]],
        ['tg_manager_chat_id', 'Telegram Chat ID менеджера', '', ['text', 50]],
        ['summer_period_start', 'Начало летнего периода (YYYY-MM-DD)', date('Y') . '-06-01', ['text', 20]],
        ['summer_period_end', 'Конец летнего периода (YYYY-MM-DD)', date('Y') . '-08-31', ['text', 20]],
        ['default_deposit', 'Сумма аванса по умолчанию (руб)', '2000', ['text', 10]],
        ['high_deposit_pavilions', 'Беседки с повышенным авансом (через запятую)', 'Теремок,Сибирская', ['text', 255]],
        ['high_deposit_amount', 'Сумма повышенного аванса (руб)', '5000', ['text', 10]],
        ['min_hours', 'Минимальное количество часов аренды', '4', ['text', 5]],
        ['weekend_restriction', 'Ограничение в выходные дни', 'no', ['selectbox', ['no' => 'Нет', 'full_day_only' => 'Только полный день']]],
        ['weekend_price_modifier', 'Модификатор цены в выходные', '1.2', ['text', 10]],
        ['holiday_dates', 'Праздничные даты (YYYY-MM-DD, через запятую)', '', ['textarea', 5]]
    ];

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && check_bitrix_sessid()) {
        foreach ($arAllOptions as $option) {
            $name = $option[0];
            $value = $_REQUEST[$name];
            \Bitrix\Main\Config\Option::set($module_id, $name, $value);
        }
    }

    $tabControl = new CAdminTabControl('tabControl', [
        ['DIV' => 'edit1', 'TAB' => 'Основные настройки', 'TITLE' => 'Основные настройки модуля'],
        ['DIV' => 'edit2', 'TAB' => 'API и интеграции', 'TITLE' => 'Настройки API и интеграций'],
        ['DIV' => 'edit3', 'TAB' => 'Уведомления', 'TITLE' => 'Настройки уведомлений'],
        ['DIV' => 'edit4', 'TAB' => 'Тарифы и цены', 'TITLE' => 'Настройки тарифов']
    ]);

?>
    <form method="post" action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= htmlspecialcharsbx($module_id) ?>&lang=<? echo LANGUAGE_ID ?>">
        <?= bitrix_sessid_post() ?>
        <?
        $tabControl->Begin();

        $tabControl->BeginNextTab();
        foreach (array_slice($arAllOptions, 0, 2) as $Option) {
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

        $tabControl->BeginNextTab();
        foreach (array_slice($arAllOptions, 2, 6) as $Option) {
            $name = $Option[0];
            $val = \Bitrix\Main\Config\Option::get($module_id, $name);
            $type = $Option[3];
        ?>
            <tr>
                <td width="40%"><?= $Option[1] ?>:</td>
                <td width="60%">
                    <? if ($type[0] == 'selectbox'): ?>
                        <select name="<?= $name ?>">
                            <? foreach ($type[1] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $val == $key ? 'selected' : '' ?>><?= $label ?></option>
                            <? endforeach; ?>
                        </select>
                    <? elseif ($type[0] == 'textarea'): ?>
                        <textarea name="<?= $name ?>" rows="3" cols="50"><?= htmlspecialcharsbx($val) ?></textarea>
                    <? else: ?>
                        <input type="<?= $type[0] ?>" name="<?= $name ?>" value="<?= htmlspecialcharsbx($val) ?>" size="<?= $type[1] ?>">
                    <? endif; ?>
                </td>
            </tr>
        <?
        }

        $tabControl->BeginNextTab();
        foreach (array_slice($arAllOptions, 8, 3) as $Option) {
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

        $tabControl->BeginNextTab();
        foreach (array_slice($arAllOptions, 11) as $Option) {
            $name = $Option[0];
            $val = \Bitrix\Main\Config\Option::get($module_id, $name);
            $type = $Option[3];
        ?>
            <tr>
                <td width="40%"><?= $Option[1] ?>:</td>
                <td width="60%">
                    <? if ($type[0] == 'selectbox'): ?>
                        <select name="<?= $name ?>">
                            <? foreach ($type[1] as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $val == $key ? 'selected' : '' ?>><?= $label ?></option>
                            <? endforeach; ?>
                        </select>
                    <? elseif ($type[0] == 'textarea'): ?>
                        <textarea name="<?= $name ?>" rows="3" cols="50"><?= htmlspecialcharsbx($val) ?></textarea>
                    <? else: ?>
                        <input type="<?= $type[0] ?>" name="<?= $name ?>" value="<?= htmlspecialcharsbx($val) ?>" size="<?= $type[1] ?>">
                    <? endif; ?>
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
<?
}
?>