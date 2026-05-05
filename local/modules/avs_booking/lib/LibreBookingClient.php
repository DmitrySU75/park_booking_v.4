<?php

namespace AVS\Booking;

class LibreBookingClient
{
    private $apiUrl;
    private $username;
    private $password;

    public function __construct()
    {
        $this->apiUrl = \Bitrix\Main\Config\Option::get('avs_booking', 'api_url', '');
        $this->username = \Bitrix\Main\Config\Option::get('avs_booking', 'api_username', '');
        $this->password = \Bitrix\Main\Config\Option::get('avs_booking', 'api_password', '');
    }

    public function updateReservation($reservationId, $newEndTime)
    {
        $api = \AVSBookingModule::getApiClient();
        return $api->updateReservationTime($reservationId, $newEndTime);
    }
}
