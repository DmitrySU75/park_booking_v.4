<?php
/**
 * Файл: /local/modules/avs_booking/error.php
 * Страница ошибки бронирования
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

<<<<<<< HEAD
=======
// Получаем ошибку из разных источников
>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
$error = '';
if (isset($_REQUEST['error'])) {
    $error = $_REQUEST['error'];
} elseif (isset($_SESSION['booking_error'])) {
    $error = $_SESSION['booking_error'];
    unset($_SESSION['booking_error']);
}

if (empty($error)) {
    $error = 'Произошла непредвиденная ошибка. Пожалуйста, попробуйте позже или свяжитесь с администратором.';
}

<<<<<<< HEAD
=======
// ЭКРАНИРУЕМ СПЕЦИАЛЬНЫЕ СИМВОЛЫ ДЛЯ ПРЕДОТВРАЩЕНИЯ XSS-АТАК
>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
$safeError = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html>
<<<<<<< HEAD
=======

>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
<head>
    <meta charset="UTF-8">
    <title>Ошибка бронирования</title>
    <style>
<<<<<<< HEAD
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .error { color: #c62828; font-size: 24px; margin-bottom: 20px; }
        .message { font-size: 18px; margin-bottom: 30px; color: #555; }
        .button { background: #2e7d32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
        .button-back { background: #757575; margin-left: 10px; }
    </style>
</head>
=======
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
        }
        .error {
            color: #c62828;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .message {
            font-size: 18px;
            margin-bottom: 30px;
            color: #555;
        }
        .button {
            background: #2e7d32;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .button-back {
            background: #757575;
            margin-left: 10px;
        }
    </style>
</head>

>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
<body>
    <div class="error">❌ Ошибка при создании бронирования</div>
    <div class="message" id="error-message"><?= $safeError ?></div>
    <div>
        <a href="javascript:history.back()" class="button button-back">← Вернуться назад</a>
        <a href="/" class="button">На главную</a>
    </div>
</body>
<<<<<<< HEAD
</html>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
=======

</html>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog.php'; ?>
>>>>>>> efab6c89e78954f385bd7a0806927d1ecb5fc2bc
