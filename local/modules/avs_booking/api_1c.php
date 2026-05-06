<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\Order;
use AVS\Booking\OneCIntegration;

CModule::IncludeModule('avs_booking');

header('Content-Type: application/json');

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$configuredKey = \Bitrix\Main\Config\Option::get('avs_booking', 'api_key', '');

if (!$configuredKey || $apiKey !== $configuredKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'export_orders':
        exportOrders();
        break;
    case 'import_status':
        importStatus();
        break;
    case 'sync_prices':
        syncPrices();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Action not found']);
}

function exportOrders()
{
    $fromDate = $_GET['from_date'] ?? date('Y-m-d', strtotime('-7 days'));
    $toDate = $_GET['to_date'] ?? date('Y-m-d');

    $orders = Order::getListByPeriod($fromDate, $toDate);

    $result = [];
    foreach ($orders as $order) {
        $result[] = [
            'order_number' => $order['ORDER_NUMBER'],
            'pavilion_name' => $order['PAVILION_NAME'],
            'client_name' => $order['CLIENT_NAME'],
            'client_phone' => $order['CLIENT_PHONE'],
            'start_time' => $order['START_TIME']->toString(),
            'end_time' => $order['END_TIME']->toString(),
            'price' => $order['PRICE'],
            'deposit' => $order['DEPOSIT_AMOUNT'],
            'status' => $order['STATUS'],
            'legal_entity' => $order['LEGAL_ENTITY']
        ];
    }

    echo json_encode(['success' => true, 'orders' => $result]);
}

function importStatus()
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['order_number']) || !isset($data['status'])) {
        echo json_encode(['success' => false, 'error' => 'Missing order_number or status']);
        return;
    }

    $order = Order::getByOrderNumber($data['order_number']);

    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        return;
    }

    $updateResult = Order::updateStatus($order['ID'], $data['status']);

    if ($updateResult) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed']);
    }
}

function syncPrices()
{
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['prices']) || !is_array($data['prices'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid prices data']);
        return;
    }

    $integration = new OneCIntegration();
    $result = $integration->syncPrices($data['prices']);

    echo json_encode($result);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
