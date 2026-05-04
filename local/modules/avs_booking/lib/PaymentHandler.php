<?php
class AVSPaymentHandler
{
    public function __construct()
    {
        // nothing
    }

    public function createPayment($bookingData, $totalPrice, $depositAmount = null)
    {
        return [
            'payment_id' => 'not_used',
            'confirmation_url' => ''
        ];
    }

    public function getPaymentStatus($paymentId)
    {
        return [
            'status' => 'unknown',
            'paid' => false,
            'amount' => 0
        ];
    }
}
