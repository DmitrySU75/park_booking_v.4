<?php
class AVSBookingLibreBookingClient
{
    private $apiUrl;
    private $username;
    private $password;
    private $cookieFile;

    public function __construct()
    {
        $this->apiUrl = \Bitrix\Main\Config\Option::get('avs_booking', 'api_url', '');
        $this->username = \Bitrix\Main\Config\Option::get('avs_booking', 'api_username', '');
        $this->password = \Bitrix\Main\Config\Option::get('avs_booking', 'api_password', '');
        $this->cookieFile = sys_get_temp_dir() . '/librebooking_cookie_' . md5($this->apiUrl);
    }

    private function authenticate()
    {
        if (!$this->apiUrl || !$this->username || !$this->password) {
            throw new Exception('API settings not configured');
        }

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

    public function checkAvailability($resourceId, $startTime, $endTime, $excludeReservationId = null)
    {
        if (!$this->authenticate()) {
            return false;
        }

        $ch = curl_init();
        $url = $this->apiUrl . '/WebService/Reservations/Availability?' . http_build_query([
            'resourceId' => $resourceId,
            'startDateTime' => $startTime,
            'endDateTime' => $endTime
        ]);

        if ($excludeReservationId) {
            $url .= '&excludeReservationId=' . $excludeReservationId;
        }

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

        throw new Exception('Reservation creation failed');
    }

    public function updateReservationTime($reservationId, $newEndTime)
    {
        if (!$this->authenticate()) {
            return false;
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

    public function moveReservation($reservationId, $newResourceId, $newStartTime, $newEndTime)
    {
        if (!$this->authenticate()) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Reservations/' . $reservationId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'resourceId' => $newResourceId,
            'startDateTime' => $newStartTime,
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
