<?php

class AVSBookingApiClient
{
    private $apiUrl;
    private $username;
    private $password;
    private $cookieFile;

    public function __construct($apiUrl, $username, $password)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/librebooking_cookie_' . md5($this->apiUrl);
    }

    private function authenticate()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Authentication/Authenticate');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $this->username,
            'password' => $this->password
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return isset($data['sessionToken']);
        }

        return false;
    }

    public function createReservation($resourceId, $startTime, $endTime, $userData)
    {
        if (!$this->authenticate()) {
            throw new Exception('Authentication failed');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Reservations');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'resourceId' => $resourceId,
            'startDateTime' => $startTime,
            'endDateTime' => $endTime,
            'firstName' => $userData['name'],
            'lastName' => '',
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'],
            'description' => $userData['comment'] ?? ''
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 || $httpCode == 201) {
            $data = json_decode($response, true);
            return $data['reservationId'] ?? null;
        }

        throw new Exception('Reservation creation failed: ' . $response);
    }

    public function checkAvailability($resourceId, $startTime, $endTime)
    {
        if (!$this->authenticate()) {
            throw new Exception('Authentication failed');
        }

        $ch = curl_init();
        $url = $this->apiUrl . '/WebService/Reservations/Availability?' . http_build_query([
            'resourceId' => $resourceId,
            'startDateTime' => $startTime,
            'endDateTime' => $endTime
        ]);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return $data['available'] ?? false;
        }

        return false;
    }

    public function getAvailableSlotsForDate($resourceId, $date)
    {
        if (!$this->authenticate()) {
            throw new Exception('Authentication failed');
        }

        $ch = curl_init();
        $url = $this->apiUrl . '/WebService/Reservations/Slots?' . http_build_query([
            'resourceId' => $resourceId,
            'date' => $date
        ]);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            return json_decode($response, true);
        }

        return [];
    }

    public function updateReservationTime($reservationId, $newEndTime)
    {
        if (!$this->authenticate()) {
            throw new Exception('Authentication failed');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Reservations/' . $reservationId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'endDateTime' => $newEndTime
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }
}
