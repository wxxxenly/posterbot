<?php
require 'config.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die('Неверная ссылка.');
}

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->prepare("SELECT id FROM users WHERE email_token = ? AND is_verified = 0");
$stmt->execute([$token]);
$user = $stmt->fetch();

if ($user) {
    $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, email_token = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    $message = "✅ Email подтверждён! Теперь вы можете войти.";
} else {
    $message = "❌ Ссылка недействительна или уже использована.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Подтверждение email</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main style="max-width: 500px; margin: 60px auto; text-align: center;">
        <h2>Подтверждение email</h2>
        <p><?= $message ?></p>
        <a href="login.php" class="btn btn-secondary">Войти</a>
    </main>
</body>
</html>