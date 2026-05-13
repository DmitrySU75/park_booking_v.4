<?php

/**
 * Файл: /local/php_interface/LibreBookingAPI.php
 * API клиент для LibreBooking
 * Версия: 4.5.0
 */

use Bitrix\Main\Config\Option;

class LibreBookingAPI
{
    private $apiUrl;
    private $username;
    private $password;
    private $cookieFile;
    private $sessionToken;
    private $userId;
    private $lastAuthTime = 0;
    private $authLifetime = 3600;

    public function __construct($apiUrl = null, $username = null, $password = null)
    {
        if ($apiUrl === null) {
            $apiUrl = Option::get('avs_booking', 'api_url', '');
        }
        if ($username === null) {
            $username = Option::get('avs_booking', 'api_username', '');
        }
        if ($password === null) {
            $password = Option::get('avs_booking', 'api_password', '');
        }

        $this->apiUrl = rtrim($apiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/librebooking_cookie_' . md5($this->apiUrl);
    }

    public function authenticate()
    {
        if ($this->sessionToken && $this->userId && (time() - $this->lastAuthTime) < $this->authLifetime) {
            return true;
        }

        if (!$this->apiUrl || !$this->username || !$this->password) {
            throw new Exception('Настройки API LibreBooking не заполнены');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/Web/Services/index.php/Authentication/Authenticate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $this->username,
            'password' => $this->password
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception('CURL Error: ' . $curlError);
        }

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            $this->sessionToken = $data['sessionToken'] ?? null;
            $this->userId = $data['userId'] ?? null;
            $this->lastAuthTime = time();
            return true;
        }

        throw new Exception('Authentication failed. HTTP Code: ' . $httpCode);
    }

    public function getSessionToken()
    {
        return $this->sessionToken;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function checkAvailability($resourceId, $startTime, $endTime, $excludeReservationId = null)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
            return false;
        }

        $ch = curl_init();
        $url = $this->apiUrl . '/Web/Services/index.php/Reservations/Availability?' . http_build_query([
            'resourceId' => $resourceId,
            'startDateTime' => $startTime,
            'endDateTime' => $endTime
        ]);

        if ($excludeReservationId) {
            $url .= '&excludeReservationId=' . $excludeReservationId;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return isset($data['available']) && $data['available'] === true;
        }

        return false;
    }

    public function getReservations($startDateTime = null, $endDateTime = null, $resourceId = null, $userId = null)
    {
        $this->authenticate();
        
        $queryParams = [];
        if ($startDateTime) $queryParams['startDateTime'] = $startDateTime;
        if ($endDateTime) $queryParams['endDateTime'] = $endDateTime;
        if ($resourceId) $queryParams['resourceId'] = $resourceId;
        if ($userId) $queryParams['userId'] = $userId;
        
        $url = $this->apiUrl . '/Web/Services/index.php/Reservations/';
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return $data['reservations'] ?? [];
        }
        
        throw new Exception('GetReservations failed. HTTP Code: ' . $httpCode);
    }

    public function getReservation($referenceNumber)
    {
        $this->authenticate();
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/Web/Services/index.php/Reservations/' . $referenceNumber);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        
        return null;
    }

    public function createReservation($resourceId, $startTime, $endTime, $userData)
    {
        $this->authenticate();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/Web/Services/index.php/Reservations/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'resourceId' => $resourceId,
            'startDateTime' => $startTime,
            'endDateTime' => $endTime,
            'firstName' => $userData['name'] ?? '',
            'lastName' => $userData['lastName'] ?? '',
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? '',
            'description' => $userData['comment'] ?? ''
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 || $httpCode == 201) {
            $data = json_decode($response, true);
            return $data['referenceNumber'] ?? null;
        }

        throw new Exception('Reservation creation failed. HTTP Code: ' . $httpCode);
    }

    public function updateReservation($referenceNumber, $startTime, $endTime, $resourceId = null)
    {
        $this->authenticate();

        $data = [
            'startDateTime' => $startTime,
            'endDateTime' => $endTime
        ];
        if ($resourceId) {
            $data['resourceId'] = $resourceId;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/Web/Services/index.php/Reservations/' . $referenceNumber);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }

    public function cancelReservation($referenceNumber)
    {
        $this->authenticate();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/Web/Services/index.php/Reservations/' . $referenceNumber);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Booked-SessionToken: ' . $this->sessionToken,
            'X-Booked-UserId: ' . $this->userId
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }

    public function getAvailableSlots($resourceId, $date)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
            return [];
        }

        $workEndHour = (int)Option::get('avs_booking', 'winter_end_hour', 22);
        $minHours = (int)Option::get('avs_booking', 'min_hours', 4);

        $slots = [];
        for ($hour = 10; $hour <= $workEndHour - $minHours; $hour++) {
            $startTime = $date . 'T' . sprintf('%02d', $hour) . ':00:00+05:00';
            $endTime = $date . 'T' . sprintf('%02d', $hour + $minHours) . ':00:00+05:00';

            if ($this->checkAvailability($resourceId, $startTime, $endTime)) {
                $slots[] = [
                    'hour' => $hour,
                    'label' => $hour . ':00',
                    'value' => $hour
                ];
            }
        }

        return $slots;
    }
}
