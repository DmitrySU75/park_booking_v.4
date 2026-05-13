<?php

/**
 * Файл: /local/modules/avs_booking/admin/price_rules.php
 * Управление ценовыми правилами (сезонные, месячные, специальные периоды)
 */

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;

$module_id = 'avs_booking';

if ($APPLICATION->GetGroupRight($module_id) < 'W') {
    $APPLICATION->AuthForm('Доступ запрещен');
}

Loader::includeModule($module_id);

$APPLICATION->SetTitle('Управление ценовыми правилами');

// Обработка сохранения
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    if ($_POST['action'] === 'save_seasonal') {
        Option::set($module_id, 'summer_hourly_price_modifier', (float)$_POST['summer_hourly_price_modifier']);
        Option::set($module_id, 'summer_full_day_price_modifier', (float)$_POST['summer_full_day_price_modifier']);
        Option::set($module_id, 'summer_night_price_modifier', (float)$_POST['summer_night_price_modifier']);
        Option::set($module_id, 'winter_hourly_price_modifier', (float)$_POST['winter_hourly_price_modifier']);
        Option::set($module_id, 'winter_full_day_price_modifier', (float)$_POST['winter_full_day_price_modifier']);
        Option::set($module_id, 'winter_night_price_modifier', (float)$_POST['winter_night_price_modifier']);
        CAdminMessage::ShowMessage(['MESSAGE' => 'Сезонные настройки сохранены', 'TYPE' => 'OK']);
    }
    
    if ($_POST['action'] === 'save_monthly') {
        $monthlyModifiers = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthlyModifiers[$month . '_hourly'] = (float)$_POST['monthly_hourly_' . $month];
            $monthlyModifiers[$month . '_full_day'] = (float)$_POST['monthly_full_day_' . $month];
            $monthlyModifiers[$month . '_night'] = (float)$_POST['monthly_night_' . $month];
        }
        Option::set($module_id, 'price_modifiers_by_month', json_encode($monthlyModifiers, JSON_UNESCAPED_UNICODE));
        CAdminMessage::ShowMessage(['MESSAGE' => 'Месячные настройки сохранены', 'TYPE' => 'OK']);
    }
    
    if ($_POST['action'] === 'add_special_period') {
        $periods = \AVS\Booking\TariffManager::getSpecialPeriods();
        
        $newPeriod = [
            'id' => time(),
            'name' => $_POST['name'],
            'active' => isset($_POST['active']),
            'date_from' => $_POST['date_from'],
            'date_to' => $_POST['date_to'],
            'rental_types' => $_POST['rental_types'] ?? [],
            'pavilion_ids' => array_map('intval', $_POST['pavilion_ids'] ?? []),
            'days_of_week' => array_map('intval', $_POST['days_of_week'] ?? []),
            'hourly_price' => $_POST['hourly_price'] ? (float)$_POST['hourly_price'] : null,
            'full_day_price' => $_POST['full_day_price'] ? (float)$_POST['full_day_price'] : null,
            'night_price' => $_POST['night_price'] ? (float)$_POST['night_price'] : null
        ];
        
        $periods[] = $newPeriod;
        \AVS\Booking\TariffManager::saveSpecialPeriods($periods);
        CAdminMessage::ShowMessage(['MESSAGE' => 'Период добавлен', 'TYPE' => 'OK']);
    }
    
    if ($_POST['action'] === 'delete_special_period' && $_POST['period_id']) {
        $periods = \AVS\Booking\TariffManager::getSpecialPeriods();
        foreach ($periods as $key => $period) {
            if ($period['id'] == $_POST['period_id']) {
                unset($periods[$key]);
                break;
            }
        }
        \AVS\Booking\TariffManager::saveSpecialPeriods(array_values($periods));
        CAdminMessage::ShowMessage(['MESSAGE' => 'Период удален', 'TYPE' => 'OK']);
    }
    
    if ($_POST['action'] === 'update_special_period' && $_POST['period_id']) {
        $periods = \AVS\Booking\TariffManager::getSpecialPeriods();
        foreach ($periods as $key => $period) {
            if ($period['id'] == $_POST['period_id']) {
                $periods[$key] = [
                    'id' => $_POST['period_id'],
                    'name' => $_POST['name'],
                    'active' => isset($_POST['active']),
                    'date_from' => $_POST['date_from'],
                    'date_to' => $_POST['date_to'],
                    'rental_types' => $_POST['rental_types'] ?? [],
                    'pavilion_ids' => array_map('intval', $_POST['pavilion_ids'] ?? []),
                    'days_of_week' => array_map('intval', $_POST['days_of_week'] ?? []),
                    'hourly_price' => $_POST['hourly_price'] ? (float)$_POST['hourly_price'] : null,
                    'full_day_price' => $_POST['full_day_price'] ? (float)$_POST['full_day_price'] : null,
                    'night_price' => $_POST['night_price'] ? (float)$_POST['night_price'] : null
                ];
                break;
            }
        }
        \AVS\Booking\TariffManager::saveSpecialPeriods($periods);
        CAdminMessage::ShowMessage(['MESSAGE' => 'Период обновлен', 'TYPE' => 'OK']);
    }
}

// Получение текущих значений
$summerHourlyModifier = (float)Option::get($module_id, 'summer_hourly_price_modifier', 1.0);
$summerFullDayModifier = (float)Option::get($module_id, 'summer_full_day_price_modifier', 1.0);
$summerNightModifier = (float)Option::get($module_id, 'summer_night_price_modifier', 1.0);
$winterHourlyModifier = (float)Option::get($module_id, 'winter_hourly_price_modifier', 1.0);
$winterFullDayModifier = (float)Option::get($module_id, 'winter_full_day_price_modifier', 1.0);
$winterNightModifier = (float)Option::get($module_id, 'winter_night_price_modifier', 1.0);

$monthlyModifiersJson = Option::get($module_id, 'price_modifiers_by_month', '');
$monthlyModifiers = json_decode($monthlyModifiersJson, true);
if (!is_array($monthlyModifiers)) {
    $monthlyModifiers = [];
}

$specialPeriods = \AVS\Booking\TariffManager::getSpecialPeriods();

// Получение списка беседок для выпадающих списков
$pavilions = [];
if (Loader::includeModule('iblock')) {
    $res = \CIBlockElement::GetList(['NAME' => 'ASC'], ['IBLOCK_ID' => 12, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME']);
    while ($el = $res->Fetch()) {
        $pavilions[$el['ID']] = $el['NAME'];
    }
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

<style>
    .price-rules-tab {
        background: #fff;
        border: 1px solid #ddd;
        margin-bottom: 20px;
        padding: 20px;
        border-radius: 4px;
    }
    .price-rules-tab h2 {
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .modifiers-table {
        width: 100%;
        border-collapse: collapse;
    }
    .modifiers-table th,
    .modifiers-table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    .modifiers-table th {
        background: #f5f5f5;
        font-weight: bold;
    }
    .modifiers-table input {
        width: 80px;
    }
    .period-card {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
    }
    .period-card .period-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #ddd;
    }
    .period-card .period-title {
        font-size: 16px;
        font-weight: bold;
    }
    .period-card .period-actions {
        display: flex;
        gap: 10px;
    }
    .period-card .period-content {
        display: none;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }
    .period-card.expanded .period-content {
        display: block;
    }
    .badge-active {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
    }
    .badge-inactive {
        background: #ffebee;
        color: #c62828;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 12px;
    }
    .form-row {
        margin-bottom: 10px;
    }
    .form-row label {
        display: inline-block;
        width: 150px;
        font-weight: bold;
    }
    .form-row input, .form-row select {
        width: 200px;
    }
    .button-small {
        padding: 3px 10px;
        font-size: 12px;
    }
</style>

<?
$tabControl = new CAdminTabControl('tabControl', [
    ['DIV' => 'seasonal', 'TAB' => 'Сезонные модификаторы', 'TITLE' => 'Лето/Зима'],
    ['DIV' => 'monthly', 'TAB' => 'Месячные модификаторы', 'TITLE' => 'По месяцам'],
    ['DIV' => 'special', 'TAB' => 'Специальные периоды', 'TITLE' => 'Праздники и акции'],
]);
?>

<form method="post" id="main_form">
    <?= bitrix_sessid_post() ?>
    
    <?
    $tabControl->Begin();
    
    // ==================== ВКЛАДКА 1: СЕЗОННЫЕ МОДИФИКАТОРЫ ====================
    $tabControl->BeginNextTab();
    ?>
    
    <div class="price-rules-tab">
        <h2>Летний период (<?= Option::get($module_id, 'summer_period_start', '01.06') ?> - <?= Option::get($module_id, 'summer_period_end', '31.08') ?>)</h2>
        <table class="modifiers-table">
            <thead>
                <tr>
                    <th>Тип аренды</th>
                    <th>Модификатор (1.0 = стандартная цена)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Почасовая</td>
                    <td><input type="text" name="summer_hourly_price_modifier" value="<?= $summerHourlyModifier ?>" size="10"> (пример: 1.2 = +20%)</td>
                </tr>
                <tr>
                    <td>Полный день</td>
                    <td><input type="text" name="summer_full_day_price_modifier" value="<?= $summerFullDayModifier ?>" size="10"></td>
                </tr>
                <tr>
                    <td>Ночь</td>
                    <td><input type="text" name="summer_night_price_modifier" value="<?= $summerNightModifier ?>" size="10"></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="price-rules-tab">
        <h2>Зимний период (<?= date('Y') . '-10-01' ?> - <?= date('Y') . '-05-31' ?>)</h2>
        <table class="modifiers-table">
            <thead>
                <tr>
                    <th>Тип аренды</th>
                    <th>Модификатор (1.0 = стандартная цена)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Почасовая</td>
                    <td><input type="text" name="winter_hourly_price_modifier" value="<?= $winterHourlyModifier ?>" size="10"></td>
                </tr>
                <tr>
                    <td>Полный день</td>
                    <td><input type="text" name="winter_full_day_price_modifier" value="<?= $winterFullDayModifier ?>" size="10"></td>
                </tr>
                <tr>
                    <td>Ночь</td>
                    <td><input type="text" name="winter_night_price_modifier" value="<?= $winterNightModifier ?>" size="10"></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div style="text-align: center; margin: 20px 0;">
        <input type="submit" name="save_seasonal" value="Сохранить сезонные настройки" class="adm-btn-save" onclick="this.form.action.value='save_seasonal'">
    </div>
    
    <?
    // ==================== ВКЛАДКА 2: МЕСЯЧНЫЕ МОДИФИКАТОРЫ ====================
    $tabControl->BeginNextTab();
    ?>
    
    <div class="price-rules-tab">
        <p>Модификаторы цен по месяцам. Значение 1.0 = стандартная цена.</p>
        <table class="modifiers-table">
            <thead>
                <tr>
                    <th>Месяц</th>
                    <th>Почасовая</th>
                    <th>Полный день</th>
                    <th>Ночь</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $months = [
                    1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель',
                    5 => 'Май', 6 => 'Июнь', 7 => 'Июль', 8 => 'Август',
                    9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь'
                ];
                for ($m = 1; $m <= 12; $m++):
                    $hourlyVal = $monthlyModifiers[$m . '_hourly'] ?? 1.0;
                    $fullDayVal = $monthlyModifiers[$m . '_full_day'] ?? 1.0;
                    $nightVal = $monthlyModifiers[$m . '_night'] ?? 1.0;
                ?>
                <tr>
                    <td><strong><?= $months[$m] ?></strong></td>
                    <td><input type="text" name="monthly_hourly_<?= $m ?>" value="<?= $hourlyVal ?>" size="8"></td>
                    <td><input type="text" name="monthly_full_day_<?= $m ?>" value="<?= $fullDayVal ?>" size="8"></td>
                    <td><input type="text" name="monthly_night_<?= $m ?>" value="<?= $nightVal ?>" size="8"></td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    
    <div style="text-align: center; margin: 20px 0;">
        <input type="submit" name="save_monthly" value="Сохранить месячные настройки" class="adm-btn-save" onclick="this.form.action.value='save_monthly'">
    </div>
    
    <?
    // ==================== ВКЛАДКА 3: СПЕЦИАЛЬНЫЕ ПЕРИОДЫ ====================
    $tabControl->BeginNextTab();
    ?>
    
    <div class="price-rules-tab">
        <h2>Добавить новый период</h2>
        <form method="post" style="margin: 0;">
            <?= bitrix_sessid_post() ?>
            <input type="hidden" name="action" value="add_special_period">
            
            <div class="form-row">
                <label>Название:</label>
                <input type="text" name="name" required size="40">
            </div>
            
            <div class="form-row">
                <label>Активен:</label>
                <input type="checkbox" name="active" value="1" checked>
            </div>
            
            <div class="form-row">
                <label>Дата начала:</label>
                <input type="date" name="date_from">
            </div>
            
            <div class="form-row">
                <label>Дата окончания:</label>
                <input type="date" name="date_to">
            </div>
            
            <div class="form-row">
                <label>Типы аренды:</label>
                <select name="rental_types[]" multiple size="3">
                    <option value="hourly">Почасовая</option>
                    <option value="full_day">Полный день</option>
                    <option value="night">Ночь</option>
                </select>
                <small>Ctrl+click для множественного выбора</small>
            </div>
            
            <div class="form-row">
                <label>Беседки (ID):</label>
                <select name="pavilion_ids[]" multiple size="5">
                    <option value="">Все беседки</option>
                    <?php foreach ($pavilions as $id => $name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialcharsbx($name) ?> (ID: <?= $id ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small>Оставьте пустым для всех беседок</small>
            </div>
            
            <div class="form-row">
                <label>Дни недели:</label>
                <select name="days_of_week[]" multiple size="7">
                    <option value="1">Понедельник</option>
                    <option value="2">Вторник</option>
                    <option value="3">Среда</option>
                    <option value="4">Четверг</option>
                    <option value="5">Пятница</option>
                    <option value="6">Суббота</option>
                    <option value="7">Воскресенье</option>
                </select>
                <small>Оставьте пустым для всех дней</small>
            </div>
            
            <div class="form-row">
                <label>Цена (почасовая):</label>
                <input type="text" name="hourly_price" size="10" placeholder="оставьте пустым">
            </div>
            
            <div class="form-row">
                <label>Цена (полный день):</label>
                <input type="text" name="full_day_price" size="10" placeholder="оставьте пустым">
            </div>
            
            <div class="form-row">
                <label>Цена (ночь):</label>
                <input type="text" name="night_price" size="10" placeholder="оставьте пустым">
            </div>
            
            <div class="form-row">
                <input type="submit" value="+ Добавить период" class="adm-btn">
            </div>
        </form>
    </div>
    
    <div class="price-rules-tab">
        <h2>Существующие периоды</h2>
        <?php if (empty($specialPeriods)): ?>
            <p>Нет добавленных периодов</p>
        <?php else: ?>
            <?php foreach ($specialPeriods as $period): ?>
                <div class="period-card" id="period_<?= $period['id'] ?>">
                    <div class="period-header">
                        <div class="period-title">
                            <?= htmlspecialcharsbx($period['name']) ?>
                            <?php if ($period['active']): ?>
                                <span class="badge-active">Активен</span>
                            <?php else: ?>
                                <span class="badge-inactive">Неактивен</span>
                            <?php endif; ?>
                        </div>
                        <div class="period-actions">
                            <button type="button" class="adm-btn button-small" onclick="togglePeriod(<?= $period['id'] ?>)">✏️ Редактировать</button>
                            <form method="post" style="display: inline-block;" onsubmit="return confirm('Удалить период?')">
                                <?= bitrix_sessid_post() ?>
                                <input type="hidden" name="action" value="delete_special_period">
                                <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                                <input type="submit" value="🗑️ Удалить" class="adm-btn button-small">
                            </form>
                        </div>
                    </div>
                    <div class="period-content">
                        <form method="post">
                            <?= bitrix_sessid_post() ?>
                            <input type="hidden" name="action" value="update_special_period">
                            <input type="hidden" name="period_id" value="<?= $period['id'] ?>">
                            
                            <div class="form-row">
                                <label>Название:</label>
                                <input type="text" name="name" value="<?= htmlspecialcharsbx($period['name']) ?>" size="40">
                            </div>
                            
                            <div class="form-row">
                                <label>Активен:</label>
                                <input type="checkbox" name="active" value="1" <?= $period['active'] ? 'checked' : '' ?>>
                            </div>
                            
                            <div class="form-row">
                                <label>Дата начала:</label>
                                <input type="date" name="date_from" value="<?= htmlspecialcharsbx($period['date_from'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <label>Дата окончания:</label>
                                <input type="date" name="date_to" value="<?= htmlspecialcharsbx($period['date_to'] ?? '') ?>">
                            </div>
                            
                            <div class="form-row">
                                <label>Типы аренды:</label>
                                <select name="rental_types[]" multiple size="3">
                                    <option value="hourly" <?= in_array('hourly', $period['rental_types'] ?? []) ? 'selected' : '' ?>>Почасовая</option>
                                    <option value="full_day" <?= in_array('full_day', $period['rental_types'] ?? []) ? 'selected' : '' ?>>Полный день</option>
                                    <option value="night" <?= in_array('night', $period['rental_types'] ?? []) ? 'selected' : '' ?>>Ночь</option>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <label>Беседки (ID):</label>
                                <select name="pavilion_ids[]" multiple size="5">
                                    <option value="">Все беседки</option>
                                    <?php foreach ($pavilions as $id => $name): ?>
                                        <option value="<?= $id ?>" <?= in_array($id, $period['pavilion_ids'] ?? []) ? 'selected' : '' ?>>
                                            <?= htmlspecialcharsbx($name) ?> (ID: <?= $id ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <label>Дни недели:</label>
                                <select name="days_of_week[]" multiple size="7">
                                    <option value="1" <?= in_array(1, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Понедельник</option>
                                    <option value="2" <?= in_array(2, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Вторник</option>
                                    <option value="3" <?= in_array(3, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Среда</option>
                                    <option value="4" <?= in_array(4, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Четверг</option>
                                    <option value="5" <?= in_array(5, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Пятница</option>
                                    <option value="6" <?= in_array(6, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Суббота</option>
                                    <option value="7" <?= in_array(7, $period['days_of_week'] ?? []) ? 'selected' : '' ?>>Воскресенье</option>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <label>Цена (почасовая):</label>
                                <input type="text" name="hourly_price" value="<?= $period['hourly_price'] ?? '' ?>" size="10">
                            </div>
                            
                            <div class="form-row">
                                <label>Цена (полный день):</label>
                                <input type="text" name="full_day_price" value="<?= $period['full_day_price'] ?? '' ?>" size="10">
                            </div>
                            
                            <div class="form-row">
                                <label>Цена (ночь):</label>
                                <input type="text" name="night_price" value="<?= $period['night_price'] ?? '' ?>" size="10">
                            </div>
                            
                            <div class="form-row">
                                <input type="submit" value="💾 Сохранить изменения" class="adm-btn-save button-small">
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <?
    $tabControl->Buttons();
    ?>
    <input type="hidden" name="action" id="form_action" value="">
    <?
    $tabControl->End();
    ?>
</form>

<script>
function togglePeriod(periodId) {
    var card = document.getElementById('period_' + periodId);
    card.classList.toggle('expanded');
}

function setAction(action) {
    document.getElementById('form_action').value = action;
}
</script>

<?
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
?>
