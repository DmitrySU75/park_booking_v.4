<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\Order;
use AVS\Booking\Payment;

CModule::IncludeModule('avs_booking');

$action = $_REQUEST['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'extend_time':
        extendTime();
        break;
    case 'create_payment':
        createPayment();
        break;
    case 'check_availability':
        checkAvailability();
        break;
    case 'get_price':
        getPrice();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

function extendTime()
{
    $orderId = intval($_REQUEST['order_id'] ?? 0);
    $newEndTime = $_REQUEST['new_end_time'] ?? '';

    if (!$orderId || !$newEndTime) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $result = Order::extendTime($orderId, $newEndTime);
    echo json_encode($result);
}

function createPayment()
{
    $orderId = intval($_REQUEST['order_id'] ?? 0);
    $returnUrl = $_REQUEST['return_url'] ?? '';

    if (!$orderId || !$returnUrl) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $result = Payment::createPayment($orderId, $returnUrl);
    echo json_encode($result);
}

function checkAvailability()
{
    $resourceId = intval($_REQUEST['resource_id'] ?? 0);
    $startTime = $_REQUEST['start_time'] ?? '';
    $endTime = $_REQUEST['end_time'] ?? '';

    if (!$resourceId || !$startTime || !$endTime) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    try {
        $api = AVSBookingModule::getApiClient();
        $available = $api->checkAvailability($resourceId, $startTime, $endTime);
        echo json_encode(['success' => true, 'available' => $available]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

function getPrice()
{
    $elementId = intval($_REQUEST['element_id'] ?? 0);
    $date = $_REQUEST['date'] ?? '';
    $priceType = $_REQUEST['price_type'] ?? 'hourly';

    if (!$elementId || !$date) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $price = AVSBookingModule::getPriceForDate($elementId, $date, $priceType);
    echo json_encode(['success' => true, 'price' => $price]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
