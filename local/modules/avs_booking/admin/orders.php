<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('avs_booking');

$APPLICATION->SetTitle('Управление бронированиями');

// Проверка прав
if ($APPLICATION->GetGroupRight('avs_booking') < 'R') {
    $APPLICATION->AuthForm('Доступ запрещен');
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'extend' && $_POST['order_id']) {
        $result = \AVS\Booking\Order::extendTime($_POST['order_id'], $_POST['new_end_time']);
        if ($result['success']) {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Время продлено', 'TYPE' => 'OK']);
        } else {
            CAdminMessage::ShowMessage(['MESSAGE' => $result['error'], 'TYPE' => 'ERROR']);
        }
    }

    if ($_POST['action'] === 'delete' && $_POST['order_id']) {
        $userId = $USER->GetID();
        if (\AVS\Booking\Order::softDelete($_POST['order_id'], $userId)) {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Заказ удален', 'TYPE' => 'OK']);
        } else {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Ошибка удаления', 'TYPE' => 'ERROR']);
        }
    }

    if ($_POST['action'] === 'status' && $_POST['order_id']) {
        if (\AVS\Booking\Order::updateStatus($_POST['order_id'], $_POST['status'])) {
            CAdminMessage::ShowMessage(['MESSAGE' => 'Статус обновлен', 'TYPE' => 'OK']);
        }
    }
}

// Получение списка заказов
$filter = [];
if ($_GET['status']) $filter['STATUS'] = $_GET['status'];
if ($_GET['pavilion']) $filter['PAVILION_NAME'] = $_GET['pavilion'];

$orders = \AVS\Booking\Order::getList($filter, 100, 0);

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>

<style>
    .order-table {
        width: 100%;
        border-collapse: collapse;
    }

    .order-table th,
    .order-table td {
        border: 1px solid #ddd;
        padding: 10px;
        text-align: left;
    }

    .order-table th {
        background: #f2f2f2;
    }

    .status-pending {
        color: #ff9800;
        font-weight: bold;
    }

    .status-paid {
        color: #4caf50;
        font-weight: bold;
    }

    .status-cancelled {
        color: #f44336;
    }

    .status-deleted {
        color: #999;
        text-decoration: line-through;
    }

    .btn-small {
        padding: 3px 8px;
        margin: 2px;
        font-size: 12px;
    }

    .extend-form {
        display: inline-block;
        margin-left: 10px;
    }
</style>

<h1>Управление бронированиями</h1>

<form method="get">
    <select name="status">
        <option value="">Все статусы</option>
        <option value="pending" <?= $_GET['status'] == 'pending' ? 'selected' : '' ?>>Ожидает оплаты</option>
        <option value="paid" <?= $_GET['status'] == 'paid' ? 'selected' : '' ?>>Оплачено</option>
        <option value="active" <?= $_GET['status'] == 'active' ? 'selected' : '' ?>>Активно</option>
        <option value="completed" <?= $_GET['status'] == 'completed' ? 'selected' : '' ?>>Завершено</option>
        <option value="cancelled" <?= $_GET['status'] == 'cancelled' ? 'selected' : '' ?>>Отменено</option>
        <option value="deleted" <?= $_GET['status'] == 'deleted' ? 'selected' : '' ?>>Удалено</option>
    </select>
    <input type="submit" value="Фильтр">
</form>

<br>

<table class="order-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Номер</th>
            <th>Беседка</th>
            <th>Клиент</th>
            <th>Телефон</th>
            <th>Начало</th>
            <th>Конец</th>
            <th>Сумма</th>
            <th>Оплачено</th>
            <th>Статус</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($orders as $order): ?>
            <?php $statusClass = '';
            switch ($order['STATUS']) {
                case 'pending':
                    $statusClass = 'status-pending';
                    break;
                case 'paid':
                    $statusClass = 'status-paid';
                    break;
                case 'deleted':
                    $statusClass = 'status-deleted';
                    break;
                case 'cancelled':
                    $statusClass = 'status-cancelled';
                    break;
            }
            ?>
            <tr class="<?= $statusClass ?>">
                <td><?= $order['ID'] ?></td>
                <td><?= htmlspecialcharsbx($order['ORDER_NUMBER']) ?></td>
                <td><?= htmlspecialcharsbx($order['PAVILION_NAME']) ?></td>
                <td><?= htmlspecialcharsbx($order['CLIENT_NAME']) ?></td>
                <td><?= htmlspecialcharsbx($order['CLIENT_PHONE']) ?></td>
                <td><?= $order['START_TIME'] ?></td>
                <td><?= $order['END_TIME'] ?></td>
                <td><?= number_format($order['PRICE'], 0, '.', ' ') ?> руб.</td>
                <td><?= number_format($order['PAID_AMOUNT'], 0, '.', ' ') ?> руб.</td>
                <td><?= $order['STATUS'] ?></td>
                <td>
                    <?php if ($order['STATUS'] != 'deleted' && $order['STATUS'] != 'cancelled'): ?>
                        <form method="post" style="display: inline-block;">
                            <?= bitrix_sessid_post() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="order_id" value="<?= $order['ID'] ?>">
                            <select name="status" onchange="this.form.submit()">
                                <option value="pending" <?= $order['STATUS'] == 'pending' ? 'selected' : '' ?>>Ожидает</option>
                                <option value="paid" <?= $order['STATUS'] == 'paid' ? 'selected' : '' ?>>Оплачено</option>
                                <option value="active" <?= $order['STATUS'] == 'active' ? 'selected' : '' ?>>Активно</option>
                                <option value="completed" <?= $order['STATUS'] == 'completed' ? 'selected' : '' ?>>Завершено</option>
                                <option value="cancelled" <?= $order['STATUS'] == 'cancelled' ? 'selected' : '' ?>>Отменить</option>
                            </select>
                        </form>

                        <form method="post" class="extend-form" onsubmit="return confirm('Продлить время?')">
                            <?= bitrix_sessid_post() ?>
                            <input type="hidden" name="action" value="extend">
                            <input type="hidden" name="order_id" value="<?= $order['ID'] ?>">
                            <input type="datetime-local" name="new_end_time" value="<?= date('Y-m-d\TH:i', strtotime($order['END_TIME'])) ?>">
                            <input type="submit" value="Продлить">
                        </form>

                        <form method="post" style="display: inline-block;" onsubmit="return confirm('Удалить заказ?')">
                            <?= bitrix_sessid_post() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="order_id" value="<?= $order['ID'] ?>">
                            <input type="submit" value="Удалить" style="background:#f44336; color:white; border:none; border-radius:3px; padding:3px 8px;">
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr>
                <td colspan="11" style="text-align:center;">Нет заказов</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
?>