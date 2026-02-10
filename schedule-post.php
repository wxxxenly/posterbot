<?php
require 'auth_check.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Только POST');
}

$user_id = $_SESSION['user_id'];
$text = trim($_POST['text'] ?? '');
$scheduled_at = $_POST['scheduled_at'] ?? null;

if (!$scheduled_at || strtotime($scheduled_at) <= time()) {
    die('Дата должна быть в будущем.');
}

$image_path = null;
if (!empty($_FILES['image']['tmp_name'])) {
    $upload_dir = __DIR__ . '/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $ext = strtolower($ext);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        die('Только изображения.');
    }
    $filename = uniqid() . '.' . $ext;
    $image_path = $upload_dir . $filename;
    move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
}

$to_vk = !empty($_POST['to_vk']) ? 1 : 0;
$to_tg = !empty($_POST['to_tg']) ? 1 : 0;

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->prepare("
    INSERT INTO posts (user_id, text, image_path, scheduled_at, to_vk, to_tg)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$user_id, $text, $image_path, $scheduled_at, $to_vk, $to_tg]);

header('Location: index.php?msg=scheduled');
exit;