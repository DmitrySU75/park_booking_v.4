<?php

namespace AVS\Booking;

class TariffManager
{
    public static function calculatePrice($pavilionName, $rentalType, $date, $hours = null, $discountCode = null)
    {
        $gazebo = \AVSBookingModule::getGazeboDataByName($pavilionName);
        if (!$gazebo) return ['error' => 'Беседка не найдена'];

        $restrictions = \AVSBookingModule::getDateRestrictions($pavilionName, $date);
        $priceModifier = $restrictions['price_modifier'] ?? 1;
        $minHours = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'min_hours', 4);

        $basePrice = 0;
        $duration = 0;

        switch ($rentalType) {
            case 'hourly':
                if ($hours < $minHours) {
                    return ['error' => "Минимальная продолжительность аренды - {$minHours} часа"];
                }
                $basePrice = $gazebo['hourly_price'];
                $duration = $hours;
                $total = $basePrice * $hours;
                break;
            case 'full_day':
                $workEndHour = \AVSBookingModule::getWorkEndHour($pavilionName, $date);
                $duration = $workEndHour - 10;
                $total = $gazebo['full_day_price'];
                break;
            case 'night':
                $duration = 8;
                $total = $gazebo['night_price'];
                break;
            default:
                return ['error' => 'Неизвестный тип аренды'];
        }

        $total = $total * $priceModifier;

        $discount = 0;
        if ($discountCode) {
            $discountInfo = DiscountManager::applyDiscount($discountCode, $total);
            if ($discountInfo['success']) {
                $discount = $discountInfo['discount_amount'];
                $total = $discountInfo['new_total'];
            }
        }

        $deposit = $gazebo['deposit_amount'];

        return [
            'success' => true,
            'base_price' => $basePrice,
            'total_price' => round($total, 2),
            'deposit_amount' => $deposit,
            'discount_amount' => round($discount, 2),
            'duration_hours' => $duration,
            'price_modifier' => $priceModifier,
            'rental_type' => $rentalType
        ];
    }

    public static function calculateExtensionPrice($orderId, $newEndTime)
    {
        $order = Order::get($orderId);
        if (!$order) {
            return ['error' => 'Заказ не найден'];
        }

        $currentEnd = new \DateTime($order['END_TIME']->toString());
        $newEnd = new \DateTime($newEndTime);

        $additionalMinutes = ($newEnd->getTimestamp() - $currentEnd->getTimestamp()) / 60;

        if ($additionalMinutes <= 0) {
            return ['error' => 'Новое время должно быть позже текущего'];
        }

        $originalDuration = $order['DURATION_HOURS'];
        $hourlyRate = $order['PRICE'] / $originalDuration;
        $additionalPrice = ($hourlyRate / 60) * $additionalMinutes;

        return [
            'success' => true,
            'additional_minutes' => $additionalMinutes,
            'additional_hours' => round($additionalMinutes / 60, 1),
            'additional_price' => round($additionalPrice, 2),
            'new_total_price' => round($order['PRICE'] + $additionalPrice, 2)
        ];
    }
}
