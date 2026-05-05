<?php

namespace AVS\Booking;

class Api
{
    private $apiKey;
    private $allowedIps = [];

    public function __construct()
    {
        $this->apiKey = \Bitrix\Main\Config\Option::get('avs_booking', 'api_key', '');
        $this->allowedIps = explode(',', \Bitrix\Main\Config\Option::get('avs_booking', 'api_allowed_ips', ''));
    }

    public function handleRequest()
    {
        header('Content-Type: application/json');

        if (!$this->checkAuth()) {
            $this->errorResponse('Unauthorized', 401);
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'create_order':
                $this->createOrder();
                break;
            case 'update_order':
                $this->updateOrder();
                break;
            case 'get_orders':
                $this->getOrders();
                break;
            case 'get_payment_info':
                $this->getPaymentInfo();
                break;
            default:
                $this->errorResponse('Action not found', 404);
        }
    }

    private function createOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$this->validateOrderData($data)) {
            $this->errorResponse('Invalid order data', 400);
            return;
        }

        $orderId = Order::create([
            'pavilion_id' => $data['pavilion_id'],
            'pavilion_name' => $data['pavilion_name'],
            'client_name' => $data['client_name'],
            'client_phone' => $data['client_phone'],
            'client_email' => $data['client_email'] ?? '',
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'price' => $data['price'],
            'comment' => $data['comment'] ?? '',
            'status' => $data['status'] ?? 'pending',
            'rental_type' => $data['rental_type'] ?? '',
            'duration_hours' => $data['duration_hours'] ?? 0
        ]);

        if ($orderId) {
            $order = Order::get($orderId);
            $this->successResponse([
                'order_id' => $orderId,
                'order_number' => $order['ORDER_NUMBER'],
                'status' => $order['STATUS']
            ]);
        } else {
            $this->errorResponse('Failed to create order', 500);
        }
    }

    private function updateOrder()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['order_id']) && !isset($data['order_number'])) {
            $this->errorResponse('Order ID or order number required', 400);
            return;
        }

        $order = null;
        if (isset($data['order_id'])) {
            $order = Order::get($data['order_id']);
        } else {
            $order = Order::getByOrderNumber($data['order_number']);
        }

        if (!$order) {
            $this->errorResponse('Order not found', 404);
            return;
        }

        $updateData = [];

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (isset($data['client_name'])) {
            $updateData['client_name'] = $data['client_name'];
        }

        if (isset($data['client_phone'])) {
            $updateData['client_phone'] = $data['client_phone'];
        }

        if (isset($data['client_email'])) {
            $updateData['client_email'] = $data['client_email'];
        }

        if (isset($data['price'])) {
            $updateData['price'] = $data['price'];
        }

        if (isset($data['comment'])) {
            $updateData['comment'] = $data['comment'];
        }

        if (isset($data['payment_status'])) {
            $updateData['payment_status'] = $data['payment_status'];
        }

        if (Order::update($order['ID'], $updateData)) {
            $updatedOrder = Order::get($order['ID']);
            $this->successResponse([
                'order_id' => $updatedOrder['ID'],
                'order_number' => $updatedOrder['ORDER_NUMBER'],
                'status' => $updatedOrder['STATUS'],
                'updated_fields' => array_keys($updateData)
            ]);
        } else {
            $this->errorResponse('Failed to update order', 500);
        }
    }

    private function getOrders()
    {
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        $legalEntity = $_GET['legal_entity'] ?? '';

        if (!$startDate || !$endDate) {
            $this->errorResponse('start_date and end_date required', 400);
            return;
        }

        $orders = Order::getListByPeriod($startDate, $endDate, $legalEntity);

        $result = [];
        foreach ($orders as $order) {
            $result[] = [
                'id' => $order['ID'],
                'order_number' => $order['ORDER_NUMBER'],
                'pavilion_id' => $order['PAVILION_ID'],
                'pavilion_name' => $order['PAVILION_NAME'],
                'legal_entity' => $order['LEGAL_ENTITY'],
                'client_name' => $order['CLIENT_NAME'],
                'client_phone' => $order['CLIENT_PHONE'],
                'client_email' => $order['CLIENT_EMAIL'],
                'start_time' => $order['START_TIME']->toString(),
                'end_time' => $order['END_TIME']->toString(),
                'price' => $order['PRICE'],
                'status' => $order['STATUS'],
                'payment_status' => $order['PAYMENT_STATUS'],
                'paid_amount' => $order['PAID_AMOUNT'],
                'created_at' => $order['CREATED_AT']->toString(),
                'rental_type' => $order['RENTAL_TYPE'],
                'duration_hours' => $order['DURATION_HOURS']
            ];
        }

        $this->successResponse(['orders' => $result, 'total' => count($result)]);
    }

    private function getPaymentInfo()
    {
        $orderId = $_GET['order_id'] ?? null;
        $orderNumber = $_GET['order_number'] ?? null;

        if (!$orderId && !$orderNumber) {
            $this->errorResponse('order_id or order_number required', 400);
            return;
        }

        $order = null;
        if ($orderId) {
            $order = Order::get($orderId);
        } else {
            $order = Order::getByOrderNumber($orderNumber);
        }

        if (!$order) {
            $this->errorResponse('Order not found', 404);
            return;
        }

        $paymentInfo = Order::getPaymentInfo($order['ID']);
        $this->successResponse($paymentInfo);
    }

    private function validateOrderData($data)
    {
        $required = ['pavilion_id', 'pavilion_name', 'client_name', 'client_phone', 'start_time', 'end_time', 'price'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    private function checkAuth()
    {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';

        if ($apiKey !== $this->apiKey) {
            return false;
        }

        $clientIp = $_SERVER['REMOTE_ADDR'];
        if (!empty($this->allowedIps) && $this->allowedIps[0] !== '' && !in_array($clientIp, $this->allowedIps)) {
            return false;
        }

        return true;
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
