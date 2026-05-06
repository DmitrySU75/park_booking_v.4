<?php

namespace AVS\Booking;

class Api
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = \Bitrix\Main\Config\Option::get('avs_booking', 'api_key', '');
    }

    public function handleRequest()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth()) {
            $this->errorResponse('Unauthorized', 401);
            return;
        }

        $action = $_GET['action'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'];

        switch ($action) {
            case 'create_order':
                if ($method === 'POST') $this->createOrder();
                break;
            case 'update_order':
                if ($method === 'POST' || $method === 'PUT') $this->updateOrder();
                break;
            case 'get_orders':
                if ($method === 'GET') $this->getOrders();
                break;
            case 'get_payment_info':
                if ($method === 'GET') $this->getPaymentInfo();
                break;
            case 'delete_order':
                if ($method === 'DELETE') $this->deleteOrder();
                break;
            case 'get_available_pavilions':
                if ($method === 'GET') $this->getAvailablePavilions();
                break;
            default:
                $this->errorResponse('Action not found', 404);
        }
    }

    private function createOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        // Валидация
        $required = ['pavilion_name', 'client_name', 'client_phone', 'start_time', 'end_time', 'rental_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->errorResponse("Missing field: {$field}", 400);
                return;
            }
        }

        // Проверка ограничений даты
        $date = substr($data['start_time'], 0, 10);
        $restrictions = \AVSBookingModule::getDateRestrictions($data['pavilion_name'], $date);
        if ($restrictions['is_special'] && !in_array($data['rental_type'], $restrictions['allowed_types'])) {
            $this->errorResponse('Данный тип аренды недоступен в выбранную дату', 400);
            return;
        }

        // Проверка доступности
        $available = self::checkAvailability($data['pavilion_name'], $data['start_time'], $data['end_time']);
        if (!$available) {
            $this->errorResponse('Выбранное время недоступно', 400);
            return;
        }

        $orderId = Order::create($data);

        if ($orderId) {
            $order = Order::get($orderId);
            $this->successResponse([
                'order_id' => $orderId,
                'order_number' => $order['ORDER_NUMBER'],
                'status' => $order['STATUS'],
                'price' => $order['PRICE'],
                'deposit_amount' => $order['DEPOSIT_AMOUNT']
            ]);
        } else {
            $this->errorResponse('Failed to create order', 500);
        }
    }

    private function updateOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $orderId = $data['order_id'] ?? 0;
        $orderNumber = $data['order_number'] ?? '';

        $order = null;
        if ($orderId) {
            $order = Order::get($orderId);
        } elseif ($orderNumber) {
            $order = Order::getByOrderNumber($orderNumber);
        }

        if (!$order) {
            $this->errorResponse('Order not found', 404);
            return;
        }

        $updateData = [];
        $allowed = ['status', 'client_name', 'client_phone', 'client_email', 'price', 'comment'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (Order::update($order['ID'], $updateData)) {
            $this->successResponse(['updated' => true, 'order_id' => $order['ID']]);
        } else {
            $this->errorResponse('Update failed', 500);
        }
    }

    private function getOrders()
    {
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $legalEntity = $_GET['legal_entity'] ?? '';
        $status = $_GET['status'] ?? '';

        $orders = Order::getListByPeriod($startDate, $endDate, $legalEntity);

        if ($status) {
            $orders = array_filter($orders, function ($o) use ($status) {
                return $o['STATUS'] === $status;
            });
        }

        $result = [];
        foreach ($orders as $order) {
            $result[] = [
                'id' => $order['ID'],
                'order_number' => $order['ORDER_NUMBER'],
                'pavilion_name' => $order['PAVILION_NAME'],
                'legal_entity' => $order['LEGAL_ENTITY'],
                'client_name' => $order['CLIENT_NAME'],
                'client_phone' => $order['CLIENT_PHONE'],
                'client_email' => $order['CLIENT_EMAIL'],
                'start_time' => $order['START_TIME']->toString(),
                'end_time' => $order['END_TIME']->toString(),
                'price' => $order['PRICE'],
                'deposit_amount' => $order['DEPOSIT_AMOUNT'],
                'paid_amount' => $order['PAID_AMOUNT'],
                'status' => $order['STATUS'],
                'payment_status' => $order['PAYMENT_STATUS'],
                'rental_type' => $order['RENTAL_TYPE'],
                'created_at' => $order['CREATED_AT']->toString()
            ];
        }

        $this->successResponse(['orders' => $result, 'total' => count($result)]);
    }

    private function getPaymentInfo()
    {
        $orderId = $_GET['order_id'] ?? 0;
        $orderNumber = $_GET['order_number'] ?? '';

        $order = null;
        if ($orderId) {
            $order = Order::get($orderId);
        } elseif ($orderNumber) {
            $order = Order::getByOrderNumber($orderNumber);
        }

        if (!$order) {
            $this->errorResponse('Order not found', 404);
            return;
        }

        $this->successResponse(Order::getPaymentInfo($order['ID']));
    }

    private function deleteOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? 0;

        if (!$orderId) {
            $this->errorResponse('order_id required', 400);
            return;
        }

        if (Order::softDelete($orderId)) {
            $this->successResponse(['deleted' => true]);
        } else {
            $this->errorResponse('Delete failed', 500);
        }
    }

    private function getAvailablePavilions()
    {
        $date = $_GET['date'] ?? date('Y-m-d');

        if (!\Bitrix\Main\Loader::includeModule('iblock')) {
            $this->errorResponse('IBlock module not loaded', 500);
            return;
        }

        $res = \CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['IBLOCK_ID' => 12, 'ACTIVE' => 'Y'],
            false,
            false,
            ['ID', 'NAME']
        );

        $pavilions = [];
        while ($el = $res->Fetch()) {
            $types = \AVSBookingModule::getAvailableRentalTypes($el['NAME'], $date);
            if (!empty($types)) {
                $pavilions[] = [
                    'id' => $el['ID'],
                    'name' => $el['NAME'],
                    'available_types' => array_keys($types)
                ];
            }
        }

        $this->successResponse(['pavilions' => $pavilions, 'date' => $date]);
    }

    private function checkAvailability($pavilionName, $startTime, $endTime)
    {
        $gazebo = \AVSBookingModule::getGazeboDataByName($pavilionName);
        if (!$gazebo || !$gazebo['resource_id']) return false;

        try {
            $client = new LibreBookingClient();
            return $client->checkAvailability($gazebo['resource_id'], $startTime, $endTime);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkAuth()
    {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        return $apiKey === $this->apiKey;
    }

    private function successResponse($data)
    {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    private function errorResponse($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
