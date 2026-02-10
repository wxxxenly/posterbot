<?php
session_start();
// Уничтожаем все данные сессии
$_SESSION = [];
session_destroy();

// Перенаправляем на страницу входа
header('Location: login.php');
exit;
