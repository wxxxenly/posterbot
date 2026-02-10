<?php
require 'auth_check.php';
require 'config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$post_id = (int)$_GET['id'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Удаляем ТОЛЬКО если это отложенный пост текущего пользователя
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ? AND scheduled_at IS NOT NULL");
    $stmt->execute([$post_id, $_SESSION['user_id']]);

    header('Location: index.php?deleted=1');
    exit;
} catch (Exception $e) {
    error_log("Delete error: " . $e->getMessage());
    header('Location: index.php?error=1');
    exit;
}