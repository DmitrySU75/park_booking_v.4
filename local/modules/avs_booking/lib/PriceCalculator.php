<?php

namespace AVS\Booking;

class PriceCalculator
{
    public static function calculate($resourceId, $startTime, $endTime, $rentalType)
    {
        $duration = self::getDurationInHours($startTime, $endTime);
        $gazebo = \AVSBookingModule::getGazeboDataByResourceId($resourceId);

        if (!$gazebo) {
            return 0;
        }

        $date = date('Y-m-d', strtotime($startTime));

        switch ($rentalType) {
            case 'hourly':
                $hourlyPrice = \AVSBookingModule::getPriceForDate($gazebo['id'], $date, 'hourly');
                return $hourlyPrice * $duration;
            case 'full_day':
                return \AVSBookingModule::getPriceForDate($gazebo['id'], $date, 'full_day');
            case 'night':
                return \AVSBookingModule::getPriceForDate($gazebo['id'], $date, 'night');
            default:
                return 0;
        }
    }

    private static function getDurationInHours($start, $end)
    {
        $startDate = new \DateTime($start);
        $endDate = new \DateTime($end);
        $diff = $startDate->diff($endDate);
        return $diff->h + ($diff->i / 60);
    }
}
