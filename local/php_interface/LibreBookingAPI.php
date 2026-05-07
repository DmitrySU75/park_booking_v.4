<?php
class LibreBookingAPI
{
    private $apiUrl;
    private $username;
    private $password;
    private $cookieFile;
    private $sessionToken;

    /**
     * Конструктор
     * @param string|null $apiUrl URL API LibreBooking
     * @param string|null $username Логин
     * @param string|null $password Пароль
     */
    public function __construct($apiUrl = null, $username = null, $password = null)
    {
        if ($apiUrl === null) {
            $apiUrl = \Bitrix\Main\Config\Option::get('avs_booking', 'api_url', '');
        }
        if ($username === null) {
            $username = \Bitrix\Main\Config\Option::get('avs_booking', 'api_username', '');
        }
        if ($password === null) {
            $password = \Bitrix\Main\Config\Option::get('avs_booking', 'api_password', '');
        }

        $this->apiUrl = rtrim($apiUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieFile = sys_get_temp_dir() . '/librebooking_cookie_' . md5($this->apiUrl);
    }

    /**
     * Аутентификация в LibreBooking
     * @return bool
     * @throws Exception
     */
    public function authenticate()
    {
        if (!$this->apiUrl || !$this->username || !$this->password) {
            throw new Exception('Настройки API LibreBooking не заполнены');
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
            return true;
        }

        throw new Exception('Authentication failed. HTTP Code: ' . $httpCode);
    }

    /**
     * Проверка доступности времени
     * @param int $resourceId ID ресурса
     * @param string $startTime Начало (Y-m-d\TH:i:sP)
     * @param string $endTime Конец (Y-m-d\TH:i:sP)
     * @param int|null $excludeReservationId ID бронирования для исключения
     * @return bool
     */
    public function checkAvailability($resourceId, $startTime, $endTime, $excludeReservationId = null)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            $data = json_decode($response, true);
            return isset($data['available']) && $data['available'] === true;
        }

        return false;
    }

    /**
     * Создание бронирования
     * @param int $resourceId ID ресурса
     * @param string $startTime Начало
     * @param string $endTime Конец
     * @param array $userData Данные пользователя
     * @return int|null ID бронирования
     * @throws Exception
     */
    public function createReservation($resourceId, $startTime, $endTime, $userData)
    {
        $this->authenticate();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Reservations');
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
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200 || $httpCode == 201) {
            $data = json_decode($response, true);
            return $data['reservationId'] ?? null;
        }

        throw new Exception('Reservation creation failed. HTTP Code: ' . $httpCode);
    }

    /**
     * Обновление времени бронирования
     * @param int $reservationId ID бронирования
     * @param string $newEndTime Новое время окончания
     * @return bool
     */
    public function updateReservationTime($reservationId, $newEndTime)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }

    /**
     * Перемещение бронирования на другой ресурс/время
     * @param int $reservationId ID бронирования
     * @param int $newResourceId Новый ID ресурса
     * @param string $newStartTime Новое время начала
     * @param string $newEndTime Новое время окончания
     * @return bool
     */
    public function moveReservation($reservationId, $newResourceId, $newStartTime, $newEndTime)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }

    /**
     * Отмена бронирования
     * @param int $reservationId ID бронирования
     * @return bool
     */
    public function cancelReservation($reservationId)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
            return false;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Reservations/' . $reservationId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode == 200;
    }

    /**
     * Получение доступных слотов для ресурса на дату
     * @param int $resourceId ID ресурса
     * @param string $date Дата (Y-m-d)
     * @return array
     */
    public function getAvailableSlots($resourceId, $date)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
            return [];
        }

        $workEndHour = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'winter_end_hour', 22);
        $minHours = (int)\Bitrix\Main\Config\Option::get('avs_booking', 'min_hours', 4);

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

    /**
     * Получение информации о ресурсе
     * @param int $resourceId ID ресурса
     * @return array|null
     */
    public function getResourceInfo($resourceId)
    {
        try {
            $this->authenticate();
        } catch (Exception $e) {
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . '/WebService/Resources/' . $resourceId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 200) {
            return json_decode($response, true);
        }

        return null;
    }

    /**
     * Получение текущей сессии
     * @return string|null
     */
    public function getSessionToken()
    {
        return $this->sessionToken;
    }
}
