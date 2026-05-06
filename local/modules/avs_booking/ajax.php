<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use AVS\Booking\Order;
use AVS\Booking\Payment;
use AVS\Booking\TariffManager;

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
    case 'apply_discount':
        applyDiscount();
        break;
    case 'get_date_restrictions':
        getDateRestrictions();
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
    $pavilionName = $_REQUEST['pavilion_name'] ?? '';
    $date = $_REQUEST['date'] ?? '';
    $rentalType = $_REQUEST['rental_type'] ?? '';
    $startHour = intval($_REQUEST['start_hour'] ?? 0);
    $hours = intval($_REQUEST['hours'] ?? 0);

    if (!$pavilionName || !$date || !$rentalType) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $timeRange = AVSBookingModule::calculateTimeRange($rentalType, $date, $pavilionName, $startHour, $hours);

    if (!$timeRange) {
        echo json_encode(['success' => false, 'available' => false, 'error' => 'Invalid time range']);
        return;
    }

    $gazebo = AVSBookingModule::getGazeboDataByName($pavilionName);
    if (!$gazebo || !$gazebo['resource_id']) {
        echo json_encode(['success' => false, 'available' => false, 'error' => 'Gazebo not found']);
        return;
    }

    try {
        $client = new AVSBookingLibreBookingClient();
        $available = $client->checkAvailability($gazebo['resource_id'], $timeRange['start'], $timeRange['end']);
        echo json_encode(['success' => true, 'available' => $available]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'available' => false, 'error' => $e->getMessage()]);
    }
}

function getPrice()
{
    $pavilionName = $_REQUEST['pavilion_name'] ?? '';
    $rentalType = $_REQUEST['rental_type'] ?? '';
    $date = $_REQUEST['date'] ?? '';
    $hours = intval($_REQUEST['hours'] ?? 0);
    $discountCode = $_REQUEST['discount_code'] ?? '';

    if (!$pavilionName || !$rentalType || !$date) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $priceData = TariffManager::calculatePrice($pavilionName, $rentalType, $date, $hours, $discountCode);
    echo json_encode($priceData);
}

function applyDiscount()
{
    $code = $_REQUEST['code'] ?? '';
    $amount = floatval($_REQUEST['amount'] ?? 0);

    if (!$code || !$amount) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $result = AVSBookingDiscountManager::applyDiscount($code, $amount);
    echo json_encode($result);
}

function getDateRestrictions()
{
    $pavilionName = $_REQUEST['pavilion_name'] ?? '';
    $date = $_REQUEST['date'] ?? '';

    if (!$pavilionName || !$date) {
        echo json_encode(['success' => false, 'error' => 'Missing parameters']);
        return;
    }

    $restrictions = AVSBookingModule::getDateRestrictions($pavilionName, $date);
    echo json_encode([
        'success' => true,
        'data' => [
            'is_special' => $restrictions['is_special'],
            'allowed_types' => $restrictions['allowed_types'],
            'price_modifier' => $restrictions['price_modifier'],
            'description' => $restrictions['description']
        ]
    ]);
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php');
