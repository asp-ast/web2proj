<?php
/**
 * api.php – Единая точка входа REST API
 * Принимает POST /api/applications (создание) и PUT /api/applications/{id} (редактирование)
 */

session_start();
require_once 'functions.php';

// Защита от Information Disclosure
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Заголовок JSON
header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$pathParts = $path ? explode('/', $path) : [];

// Проверка CSRF для методов, изменяющих данные (POST, PUT)
if (in_array($method, ['POST', 'PUT'])) {
    // Токен ищем в заголовке X-CSRF-TOKEN или в теле запроса (csrf_token)
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!checkCsrfToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Неверный CSRF-токен']);
        exit;
    }
}

try {
    // POST /api/applications — создание новой заявки (неавторизованный доступ)
    if ($method === 'POST' && $path === 'applications') {
        // Данные могут прийти как JSON или как обычная форма
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $errors = validate($input);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            exit;
        }
        $appId = saveApplication($pdo, $input, null);
        $credentials = generateCredentials($pdo, $appId);
        // Устанавливаем cookies для fallback (если браузер не поддерживает JS)
        setcookie('saved_data', json_encode($input), time() + 31536000, '/');
        echo json_encode([
            'success'     => true,
            'login'       => $credentials['login'],
            'password'    => $credentials['password'],
            'profile_url' => 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php'
        ]);
        exit;
    }

    // PUT /api/applications/{id} — редактирование заявки (только после входа)
    if ($method === 'PUT' && isset($pathParts[0], $pathParts[1]) && $pathParts[0] === 'applications') {
        if (!isset($_SESSION['app_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Необходима авторизация']);
            exit;
        }
        $editId = (int)$pathParts[1];
        if ($editId !== $_SESSION['app_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'Доступ запрещён']);
            exit;
        }
        // Получаем данные
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        // Исключаем логин и пароль из обновления (они не должны меняться)
        unset($input['login'], $input['password']);
        $errors = validate($input);
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode(['errors' => $errors]);
            exit;
        }
        saveApplication($pdo, $input, $editId);
        echo json_encode(['success' => true]);
        exit;
    }

    // Если маршрут не найден
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
} catch (Exception $e) {
    error_log("API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Внутренняя ошибка сервера']);
}