<?php
// Общие функции (БД, валидация, CSRF, безопасность)

// ================= ЗАЩИТА ОТ УТЕЧКИ ИНФОРМАЦИИ =================
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/home/u82674/php_errors.log');

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $db_host = 'localhost';
        $db_user = 'u82674';
        $db_pass = '7581119'; // ← замените на ваш пароль от MySQL
        $db_name = 'u82674';
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user, $db_pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Для дополнительной защиты от SQL-инъекций отключаем эмуляцию
                    PDO::ATTR_EMULATE_PREPARES   => false
                ]
            );
        } catch (PDOException $e) {
            error_log("DB connection error: " . $e->getMessage());
            die("Ошибка сервера. Попробуйте позже.");
        }
    }
    return $pdo;
}

/** Безопасный вывод строки */
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/** Валидация полей формы */
function validate(array $data): array {
    $errors = [];
    $full_name = $data['full_name'] ?? '';
    if ($full_name === '') {
        $errors['full_name'] = 'Поле обязательно.';
    } elseif (!preg_match('/^[A-Za-zА-Яа-яЁё\s]+$/u', $full_name)) {
        $errors['full_name'] = 'Допустимы только буквы и пробелы.';
    } elseif (preg_match_all('/./u', $full_name) > 150) {
        $errors['full_name'] = 'Не более 150 символов.';
    }
    $phone = $data['phone'] ?? '';
    if ($phone === '') {
        $errors['phone'] = 'Поле обязательно.';
    } elseif (!preg_match('/^\+?\d{1,3}[\s\-]?\(?\d{1,4}\)?[\s\-]?\d{1,4}[\s\-]?\d{1,9}$/', $phone)) {
        $errors['phone'] = 'Допустимы цифры, +, дефис, скобки, пробелы.';
    }
    $email = $data['email'] ?? '';
    if ($email === '') {
        $errors['email'] = 'Поле обязательно.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный адрес.';
    }
    $birth = $data['birth_date'] ?? '';
    if ($birth === '') {
        $errors['birth_date'] = 'Поле обязательно.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
        $errors['birth_date'] = 'Формат ГГГГ-ММ-ДД.';
    }
    $gender = $data['gender'] ?? '';
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Выберите значение.';
    }
    $languages = $data['languages'] ?? [];
    if (!is_array($languages) || count($languages) === 0) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        foreach ($languages as $lid) {
            if (!in_array((int)$lid, range(1, 12))) {
                $errors['languages'] = 'Недопустимый язык.';
                break;
            }
        }
    }
    $contract = $data['contract_agreed'] ?? '';
    if ($contract !== '1') {
        $errors['contract'] = 'Необходимо подтвердить ознакомление.';
    }
    return $errors;
}

/** Сохранение/обновление заявки */
function saveApplication(PDO $pdo, array $data, ?int $appId = null): int {
    $pdo->beginTransaction();
    try {
        if ($appId) {
            $stmt = $pdo->prepare("UPDATE application SET
                full_name = :f, phone = :p, email = :e, birth_date = :b,
                gender = :g, biography = :bio, contract_agreed = :c
                WHERE id = :id");
            $stmt->execute([
                ':f' => $data['full_name'], ':p' => $data['phone'],
                ':e' => $data['email'], ':b' => $data['birth_date'],
                ':g' => $data['gender'], ':bio' => $data['biography'] ?? '',
                ':c' => ($data['contract_agreed'] === '1') ? 1 : 0,
                ':id' => $appId
            ]);
            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$appId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO application
                (full_name, phone, email, birth_date, gender, biography, contract_agreed)
                VALUES (:f, :p, :e, :b, :g, :bio, :c)");
            $stmt->execute([
                ':f' => $data['full_name'], ':p' => $data['phone'],
                ':e' => $data['email'], ':b' => $data['birth_date'],
                ':g' => $data['gender'], ':bio' => $data['biography'] ?? '',
                ':c' => ($data['contract_agreed'] === '1') ? 1 : 0
            ]);
            $appId = $pdo->lastInsertId();
        }
        if (!empty($data['languages'])) {
            $stmtL = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($data['languages'] as $lid) {
                $stmtL->execute([$appId, (int)$lid]);
            }
        }
        $pdo->commit();
        return $appId;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Save error: " . $e->getMessage());
        throw new Exception("Ошибка сохранения данных");
    }
}

/** Загрузка заявки */
function loadApplication(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = ?");
    $stmt->execute([$id]);
    $app = $stmt->fetch();
    if (!$app) return null;
    $stmtL = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = ?");
    $stmtL->execute([$id]);
    $app['languages'] = array_map('intval', $stmtL->fetchAll(PDO::FETCH_COLUMN));
    return $app;
}

/** Генерация учётных данных */
function generateCredentials(PDO $pdo, int $id): array {
    $login = 'user' . $id;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM application WHERE login = ?");
    $stmt->execute([$login]);
    if ($stmt->fetchColumn() > 0) {
        $login .= '_' . bin2hex(random_bytes(2));
    }
    $password = bin2hex(random_bytes(4));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE application SET login = ?, password_hash = ? WHERE id = ?")
        ->execute([$login, $hash, $id]);
    return ['login' => $login, 'password' => $password];
}

/** Аутентификация пользователя приложения */
function authenticateApplication(PDO $pdo, string $login, string $password): ?int {
    $stmt = $pdo->prepare("SELECT id, password_hash FROM application WHERE login = ?");
    $stmt->execute([$login]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user['id'];
    }
    return null;
}

/* ======== CSRF ======== */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrfToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}