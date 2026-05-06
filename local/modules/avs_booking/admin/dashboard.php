<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use AVS\Booking\Order;

Loader::includeModule('avs_booking');

$APPLICATION->SetTitle('Дашборд AVS Booking');

if ($APPLICATION->GetGroupRight('avs_booking') < 'R') {
    $APPLICATION->AuthForm('Доступ запрещен');
}

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-7 days'));
$monthAgo = date('Y-m-d', strtotime('-30 days'));

$weekOrders = Order::getListByPeriod($weekAgo, $today);
$monthOrders = Order::getListByPeriod($monthAgo, $today);
$todayOrders = Order::getListByPeriod($today, $today);

$totalWeek = array_sum(array_column($weekOrders, 'PRICE'));
$totalMonth = array_sum(array_column($monthOrders, 'PRICE'));
$paidWeek = array_sum(array_column(array_filter($weekOrders, function ($o) {
    return $o['STATUS'] == 'paid';
}), 'PRICE'));

$statusCount = [
    'pending' => 0,
    'paid' => 0,
    'active' => 0,
    'completed' => 0,
    'cancelled' => 0
];

foreach ($weekOrders as $order) {
    $statusCount[$order['STATUS']]++;
}

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

<style>
    .dashboard-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .stat-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        min-width: 200px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #666;
    }

    .stat-card .value {
        font-size: 28px;
        font-weight: bold;
        color: #333;
    }

    .stat-card .unit {
        font-size: 14px;
        color: #999;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }

    .status-pending {
        background: #fff3e0;
        color: #ff9800;
    }

    .status-paid {
        background: #e8f5e9;
        color: #4caf50;
    }

    .status-active {
        background: #e3f2fd;
        color: #2196f3;
    }

    .status-completed {
        background: #e0f2f1;
        color: #009688;
    }

    .status-cancelled {
        background: #ffebee;
        color: #f44336;
    }
</style>

<h1>Статистика бронирований</h1>

<div class="dashboard-stats">
    <div class="stat-card">
        <h3>Бронирований за неделю</h3>
        <div class="value"><?= count($weekOrders) ?></div>
    </div>
    <div class="stat-card">
        <h3>На сумму (неделя)</h3>
        <div class="value"><?= number_format($totalWeek, 0, '.', ' ') ?></div>
        <div class="unit">руб.</div>
    </div>
    <div class="stat-card">
        <h3>Оплачено (неделя)</h3>
        <div class="value"><?= number_format($paidWeek, 0, '.', ' ') ?></div>
        <div class="unit">руб.</div>
    </div>
    <div class="stat-card">
        <h3>Бронирований за месяц</h3>
        <div class="value"><?= count($monthOrders) ?></div>
    </div>
    <div class="stat-card">
        <h3>На сумму (месяц)</h3>
        <div class="value"><?= number_format($totalMonth, 0, '.', ' ') ?></div>
        <div class="unit">руб.</div>
    </div>
</div>

<h2>Бронирования сегодня (<?= count($todayOrders) ?>)</h2>
<table class="order-table" style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            <th>Время</th>
            <th>Беседка</th>
            <th>Клиент</th>
            <th>Сумма</th>
            <th>Статус</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($todayOrders as $order): ?>
            <tr>
            <tr><?= date('H:i', strtotime($order['START_TIME'])) ?> - <?= date('H:i', strtotime($order['END_TIME'])) ?></td>
                <td><?= htmlspecialcharsbx($order['PAVILION_NAME']) ?></td>
                <td><?= htmlspecialcharsbx($order['CLIENT_NAME']) ?></td>
                <td><?= number_format($order['PRICE'], 0, '.', ' ') ?> руб.</td>
                <td><span class="status-badge status-<?= $order['STATUS'] ?>"><?= $order['STATUS'] ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($todayOrders)): ?>
            <tr>
                <td colspan="5" style="text-align:center;">Нет бронирований на сегодня</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<h2>Статусы бронирований за неделю</h2>
<table class="order-table" style="width:auto;">
    <thead>
        <tr>
            <th>Статус</th>
            <th>Количество</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><span class="status-badge status-pending">Ожидает оплаты</span></td>
            <td><?= $statusCount['pending'] ?></td>
        </tr>
        <tr>
            <td><span class="status-badge status-paid">Оплачено</span></td>
            <td><?= $statusCount['paid'] ?></td>
        </tr>
        <tr>
            <td><span class="status-badge status-active">Активно</span></td>
            <td><?= $statusCount['active'] ?></td>
        </tr>
        <tr>
            <td><span class="status-badge status-completed">Завершено</span></td>
            <td><?= $statusCount['completed'] ?></td>
        </tr>
        <tr>
            <td><span class="status-badge status-cancelled">Отменено</span></td>
            <td><?= $statusCount['cancelled'] ?></td>
        </tr>
    </tbody>
</table>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
?>