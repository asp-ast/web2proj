<?php
require_once 'functions.php';

$pdo = getPDO();

// ---------- HTTP Basic Auth ----------
$validUser = null;
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $login = $_SERVER['PHP_AUTH_USER'];
    $password = $_SERVER['PHP_AUTH_PW'];

    $stmt = $pdo->prepare("SELECT id, password_hash FROM admin_users WHERE login = ?");
    $stmt->execute([$login]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $validUser = $admin['id'];
    }
}

if (!$validUser) {
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Необходима авторизация администратора.';
    exit;
}

// ---------- Переменные ----------
$message = '';
$editMode = false;
$editData = [];
$errors = [];

// Удаление записи
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM application WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $message = "Запись #$id удалена.";
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Admin delete error: " . $e->getMessage());
        $message = "Ошибка удаления записи.";
    }
}

// Загрузка формы редактирования
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $app = loadApplication($pdo, $editId);
    if ($app) {
        $editMode = true;
        $editData = $app;
    }
}

// Сохранение отредактированной записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    if (!checkCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Неверный CSRF-токен.');
    }

    $form = [
        'full_name'       => trim($_POST['full_name'] ?? ''),
        'phone'           => trim($_POST['phone'] ?? ''),
        'email'           => trim($_POST['email'] ?? ''),
        'birth_date'      => trim($_POST['birth_date'] ?? ''),
        'gender'          => $_POST['gender'] ?? '',
        'languages'       => $_POST['languages'] ?? [],
        'biography'       => trim($_POST['biography'] ?? ''),
        'contract_agreed' => $_POST['contract_agreed'] ?? ''
    ];
    $errors = validate($form);

    if (empty($errors)) {
        try {
            saveApplication($pdo, $form, (int)$_POST['edit_id']);
            $message = "Запись обновлена.";
            $editMode = false;
            $editData = [];
        } catch (Exception $e) {
            error_log("Admin save error: " . $e->getMessage());
            $message = "Ошибка при обновлении записи.";
        }
    } else {
        $editMode = true;
        $editData = $form;
    }
}

// ---------- Получение всех записей ----------
$stmt = $pdo->query("SELECT * FROM application ORDER BY id DESC");
$applications = $stmt->fetchAll();

foreach ($applications as &$app) {
    $stmtLang = $pdo->prepare("SELECT pl.name FROM application_languages al 
                               JOIN programming_languages pl ON al.language_id = pl.id 
                               WHERE al.application_id = ?");
    $stmtLang->execute([$app['id']]);
    $app['langs'] = implode(', ', $stmtLang->fetchAll(PDO::FETCH_COLUMN));
}
unset($app);

// ---------- Статистика по языкам ----------
$stmtStats = $pdo->query("SELECT pl.name, COUNT(DISTINCT al.application_id) AS cnt
                          FROM programming_languages pl
                          LEFT JOIN application_languages al ON pl.id = al.language_id
                          GROUP BY pl.id, pl.name
                          ORDER BY cnt DESC");
$stats = $stmtStats->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель – u82674</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Панель администратора</h1>
        <p><a href="index.php">← Вернуться к пользовательской форме</a></p>

        <?php if ($message): ?>
            <div class="success-block"><?= h($message) ?></div>
        <?php endif; ?>

        <?php if ($editMode): ?>
            <hr>
            <h2>Редактирование записи #<?= (int)($_POST['edit_id'] ?? $_GET['edit']) ?></h2>
            <?php if (!empty($errors)): ?>
                <div class="error-block">
                    <ul>
                        <?php foreach ($errors as $field => $msg): ?>
                            <li><strong><?= h($field) ?>:</strong> <?= h($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
                <div class="form-group">
                    <label>ФИО</label>
                    <input type="text" name="full_name" value="<?= h($editData['full_name'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="text" name="phone" value="<?= h($editData['phone'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= h($editData['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Дата рождения</label>
                    <input type="date" name="birth_date" value="<?= h($editData['birth_date'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Пол</label>
                    <select name="gender">
                        <option value="male" <?= ($editData['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Мужской</option>
                        <option value="female" <?= ($editData['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Женский</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Любимые языки</label>
                    <select name="languages[]" multiple size="8" required>
                        <?php
                        $langNames = [
                            1 => 'Pascal', 2 => 'C', 3 => 'C++', 4 => 'JavaScript',
                            5 => 'PHP', 6 => 'Python', 7 => 'Java', 8 => 'Haskell',
                            9 => 'Clojure', 10 => 'Prolog', 11 => 'Scala', 12 => 'Go'
                        ];
                        $selected = $editData['languages'] ?? [];
                        foreach ($langNames as $id => $name):
                            $sel = in_array($id, $selected) ? 'selected' : '';
                        ?>
                            <option value="<?= $id ?>" <?= $sel ?>><?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Биография</label>
                    <textarea name="biography"><?= h($editData['biography'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="contract_agreed" value="1" <?= ($editData['contract_agreed'] ?? '') == '1' ? 'checked' : '' ?>>
                        С контрактом ознакомлен(а)
                    </label>
                </div>
                <button type="submit">Сохранить изменения</button>
                <a href="admin.php">Отмена</a>
            </form>
            <hr>
        <?php endif; ?>

        <h2>Все заявки</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рожд.</th>
                <th>Пол</th>
                <th>Языки</th>
                <th>Биография</th>
                <th>Контракт</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?= $app['id'] ?></td>
                <td><?= h($app['full_name']) ?></td>
                <td><?= h($app['phone']) ?></td>
                <td><?= h($app['email']) ?></td>
                <td><?= h($app['birth_date']) ?></td>
                <td><?= $app['gender'] === 'male' ? 'М' : 'Ж' ?></td>
                <td><?= h($app['langs'] ?? '') ?></td>
                <td><?= h(strlen($app['biography'] ?? '') > 50 ? substr($app['biography'], 0, 50) . '…' : ($app['biography'] ?? '')) ?></td>
                <td><?= $app['contract_agreed'] ? 'Да' : 'Нет' ?></td>
                <td>
                    <a href="?edit=<?= $app['id'] ?>">Редактировать</a><br>
                    <a href="?delete=<?= $app['id'] ?>" onclick="return confirm('Удалить запись #<?= $app['id'] ?>?')">Удалить</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h2>Статистика: количество пользователей по языкам</h2>
        <table>
            <tr><th>Язык</th><th>Кол-во пользователей</th></tr>
            <?php foreach ($stats as $row): ?>
            <tr>
                <td><?= h($row['name']) ?></td>
                <td><?= $row['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>