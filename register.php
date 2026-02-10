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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_POST['username'] ?? false) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Ошибка безопасности.');
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    // Валидация
    if (strlen($username) < 3) {
        $error = "Логин от 3 символов.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Неверный email.";
    } elseif (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Пароль: 8+ символов, буквы и цифры.";
    } else {
        try {
            require 'config.php';
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

            // Проверка уникальности
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $error = "Логин или email уже используются.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $token = bin2hex(random_bytes(32)); // токен подтверждения

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, email_token, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $email, $hash, $token]);

                // Отправка письма
                require_once 'mailer.php';
                $subject = 'Подтвердите регистрацию';
                $link = "https://ваш_домен.ru/verify.php?token=" . urlencode($token);
                $body = "Здравствуйте!\n\nПерейдите по ссылке, чтобы подтвердить регистрацию:\n$link\n\nЕсли вы не регистрировались, просто проигнорируйте это письмо.";

                if (send_email($email, $subject, $body)) {
                    $_SESSION['awaiting_verification'] = true;
                    header('Location: check-email.php');
                    exit;
                } else {
                    $error = "Не удалось отправить письмо. Попробуйте позже.";
                }
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
    <title>Регистрация</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main style="max-width: 400px; margin: 40px auto;">
        <h2>Регистрация</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post">
            <input name="username" placeholder="Логин (от 3 символов)" required minlength="3">
            <input name="email" type="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Пароль (8+ символов)" required minlength="8">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button type="submit">Зарегистрироваться</button>
        </form>
        <p><a href="login.php">Уже есть аккаунт?</a></p>
    </main>
</body>
</html>