<?php

namespace AVS\Booking;

class AdminExtension
{
    public static function extendBookingTime($orderId, $newEndTime)
    {
        return Order::extendTime($orderId, $newEndTime);
    }

    public static function getOrdersList($filter = [], $limit = 50)
    {
        return Order::getList($filter, $limit, 0);
    }

    public static function updateOrderStatus($orderId, $status)
    {
        return Order::updateStatus($orderId, $status);
    }
}
