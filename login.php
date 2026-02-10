<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// === Защита от брутфорса ===
$ip = $_SERVER['REMOTE_ADDR'];
$attempts_file = __DIR__ . '/attempts/' . $ip . '.txt';
$now = time();

if (file_exists($attempts_file)) {
    $data = json_decode(file_get_contents($attempts_file), true);
    if ($data['count'] >= 5 && ($now - $data['last']) < 900) {
        die('Слишком много попыток. Попробуйте через 15 минут.');
    }
}

// === CSRF ===
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_POST['username'] ?? false) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Ошибка безопасности.');
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = "Заполните все поля.";
    } else {
        try {
            require 'config.php';
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $stmt = $pdo->prepare("SELECT id, password_hash, is_verified FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user) {
                if (!$user['is_verified']) {
                    $error = "Сначала подтвердите email.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    // Успех
                    if (file_exists($attempts_file)) unlink($attempts_file);
                    $_SESSION['user_id'] = $user['id'];
                    session_regenerate_id(true);
                    header('Location: index.php');
                    exit;
                } else {
                    // Неверный пароль
                    $count = file_exists($attempts_file) ? json_decode(file_get_contents($attempts_file), true)['count'] + 1 : 1;
                    file_put_contents($attempts_file, json_encode(['count' => $count, 'last' => $now]));
                    $error = "Неверный логин или пароль";
                }
            } else {
                // Нет такого пользователя
                $count = file_exists($attempts_file) ? json_decode(file_get_contents($attempts_file), true)['count'] + 1 : 1;
                file_put_contents($attempts_file, json_encode(['count' => $count, 'last' => $now]));
                $error = "Неверный логин или пароль";
            }
        } catch (Exception $e) {
            $error = "Ошибка сервера";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main style="max-width: 400px; margin: 40px auto;">
        <h2>Вход</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <input name="username" placeholder="Логин" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit">Войти</button>
        </form>
        <p><a href="register.php">Регистрация</a></p>
    </main>
</body>
</html>