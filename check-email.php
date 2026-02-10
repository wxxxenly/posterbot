<?php
session_start();
if (!($_SESSION['awaiting_verification'] ?? false)) {
    header('Location: login.php');
    exit;
}
unset($_SESSION['awaiting_verification']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Проверьте почту</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <main style="max-width: 500px; margin: 60px auto; text-align: center;">
        <h2>Почти готово!</h2>
        <p>Мы отправили письмо на ваш email.</p>
        <p>Перейдите по ссылке в письме, чтобы завершить регистрацию.</p>
        <a href="login.php" class="btn btn-secondary">Войти</a>
    </main>
</body>
</html>