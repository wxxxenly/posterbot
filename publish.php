<?php
require 'auth_check.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Только POST');
}

$action = $_POST['action'] ?? 'publish';
$user_id = $_SESSION['user_id'];
$text = trim($_POST['text'] ?? '');
$scheduled_at = $_POST['scheduled_at'] ?? null;

// === ВАЛИДАЦИЯ: должен быть либо текст, либо изображения ===
$has_text = !empty($text);
$has_images = !empty($_FILES['images']['tmp_name'][0]);

if (!$has_text && !$has_images) {
    header('Location: index.php?error=empty_post');
    exit;
}

// Создаём папку uploads, если её нет
$upload_dir = __DIR__ . '/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Обработка изображений
$image_path = null;
$additional_images = null;
$allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

if ($has_images) {
    $files = $_FILES['images'];
    $count = count($files['tmp_name']);
    $saved_files = [];

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $tmp_name = $files['tmp_name'][$i];
        $name = $files['name'][$i];
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $ext = strtolower($ext);

        if (!in_array($ext, $allowed_ext)) {
            header('Location: index.php?error=invalid_image');
            exit;
        }

        $filename = uniqid() . '.' . $ext;
        $filepath = $upload_dir . $filename;

        if (!move_uploaded_file($tmp_name, $filepath)) {
            header('Location: index.php?error=upload_failed');
            exit;
        }

        $saved_files[] = $filename;
    }

    if (!empty($saved_files)) {
        $image_path = $saved_files[0];
        if (count($saved_files) > 1) {
            $additional_images = json_encode(array_slice($saved_files, 1));
        }
    }
}

$to_vk = !empty($_POST['to_vk']) ? 1 : 0;
$to_tg = !empty($_POST['to_tg']) ? 1 : 0;

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($action === 'schedule') {
    if (!$scheduled_at || strtotime($scheduled_at) <= time()) {
        header('Location: index.php?error=invalid_date');
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, text, image_path, additional_images, scheduled_at, to_vk, to_tg, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    $stmt->execute([$user_id, $text, $image_path, $additional_images, $scheduled_at, $to_vk, $to_tg]);
    header('Location: index.php?msg=scheduled');
} else {
    $stmt = $pdo->prepare("
        INSERT INTO posts (user_id, text, image_path, additional_images, to_vk, to_tg, status)
        VALUES (?, ?, ?, ?, ?, ?, 'published')
    ");
    $stmt->execute([$user_id, $text, $image_path, $additional_images, $to_vk, $to_tg]);
    $post_id = $pdo->lastInsertId();

    $vk_ok = false;
    $tg_ok = false;

    if ($to_vk) {
        $stmt = $pdo->prepare("SELECT vk_access_token, vk_group_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $vk_user = $stmt->fetch();
        if ($vk_user && $vk_user['vk_access_token'] && $vk_user['vk_group_id']) {
            $vk_ok = publishToVK($text, $image_path, $additional_images, $vk_user['vk_access_token'], $vk_user['vk_group_id']);
        }
    }

    if ($to_tg) {
        $stmt = $pdo->prepare("SELECT tg_bot_token, tg_chat_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $tg_user = $stmt->fetch();
        if ($tg_user && $tg_user['tg_bot_token'] && $tg_user['tg_chat_id']) {
            $tg_ok = publishToTelegram($text, $image_path, $additional_images, $tg_user['tg_bot_token'], $tg_user['tg_chat_id']);
        }
    }

    $stmt = $pdo->prepare("UPDATE posts SET vk_posted = ?, tg_posted = ? WHERE id = ?");
    $stmt->execute([(int)(bool)$vk_ok, (int)(bool)$tg_ok, $post_id]);

    header('Location: index.php?msg=published');
}
exit;

// === Функции публикации ===
function publishToVK($text, $image_path, $additional_images, $access_token, $group_id) {
    $url = "https://api.vk.com/method/";
    $attachments = [];

    // Основное изображение
    if ($image_path && file_exists(__DIR__ . '/uploads/' . $image_path)) {
        $server_url = $url . "photos.getWallUploadServer?access_token=$access_token&v=5.199&group_id=$group_id";
        $server_resp = file_get_contents($server_url);
        $server_data = json_decode($server_resp, true);
        if ($server_data && isset($server_data['response']['upload_url'])) {
            $ch = curl_init($server_data['response']['upload_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['photo' => new CURLFile(__DIR__ . '/uploads/' . $image_path)]);
            $upload_result = curl_exec($ch);
            curl_close($ch);
            $photo_data = json_decode($upload_result, true);
            if ($photo_data && isset($photo_data['photo'])) {
                $save_url = $url . "photos.saveWallPhoto?access_token=$access_token&v=5.199&group_id=$group_id&photo={$photo_data['photo']}&server={$photo_data['server']}&hash={$photo_data['hash']}";
                $save_resp = file_get_contents($save_url);
                $saved = json_decode($save_resp, true);
                if ($saved && isset($saved['response'][0]['id'])) {
                    $attachments[] = "photo-{$group_id}_{$saved['response'][0]['id']}";
                }
            }
        }
    }

    // Дополнительные изображения
    if ($additional_images) {
        $extra_files = json_decode($additional_images, true);
        foreach ($extra_files as $file) {
            if (file_exists(__DIR__ . '/uploads/' . $file)) {
                $server_url = $url . "photos.getWallUploadServer?access_token=$access_token&v=5.199&group_id=$group_id";
                $server_resp = file_get_contents($server_url);
                $server_data = json_decode($server_resp, true);
                if ($server_data && isset($server_data['response']['upload_url'])) {
                    $ch = curl_init($server_data['response']['upload_url']);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, ['photo' => new CURLFile(__DIR__ . '/uploads/' . $file)]);
                    $upload_result = curl_exec($ch);
                    curl_close($ch);
                    $photo_data = json_decode($upload_result, true);
                    if ($photo_data && isset($photo_data['photo'])) {
                        $save_url = $url . "photos.saveWallPhoto?access_token=$access_token&v=5.199&group_id=$group_id&photo={$photo_data['photo']}&server={$photo_data['server']}&hash={$photo_data['hash']}";
                        $save_resp = file_get_contents($save_url);
                        $saved = json_decode($save_resp, true);
                        if ($saved && isset($saved['response'][0]['id'])) {
                            $attachments[] = "photo-{$group_id}_{$saved['response'][0]['id']}";
                        }
                    }
                }
            }
        }
    }

    $params = [
        'access_token' => $access_token,
        'v' => '5.199',
        'owner_id' => "-$group_id",
        'message' => $text,
        'from_group' => 1
    ];
    if (!empty($attachments)) {
        $params['attachments'] = implode(',', $attachments);
    }

    $post_url = $url . "wall.post?" . http_build_query($params);
    $post_resp = file_get_contents($post_url);
    $result = json_decode($post_resp, true);
    return $result && !isset($result['error']);
}

function publishToTelegram($text, $image_path, $additional_images, $bot_token, $chat_id) {
    $url = "https://api.telegram.org/bot$bot_token/";

    if ($image_path && file_exists(__DIR__ . '/uploads/' . $image_path)) {
        // Основное изображение с текстом
        $ch = curl_init($url . "sendPhoto");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'caption' => $text ?: '',
            'photo' => new CURLFile(__DIR__ . '/uploads/' . $image_path)
        ]);
        $result = curl_exec($ch);
        curl_close($ch);

        // Дополнительные изображения без подписи
        if ($additional_images) {
            $extra_files = json_decode($additional_images, true);
            foreach ($extra_files as $file) {
                if (file_exists(__DIR__ . '/uploads/' . $file)) {
                    $ch = curl_init($url . "sendPhoto");
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, [
                        'chat_id' => $chat_id,
                        'photo' => new CURLFile(__DIR__ . '/uploads/' . $file)
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        }

        return $result !== false;
    } else {
        // Только текст
        if ($text) {
            $msg_url = $url . "sendMessage?chat_id=" . urlencode($chat_id) . "&text=" . urlencode($text);
            $result = file_get_contents($msg_url);
            return $result !== false;
        }
        return false;
    }
}
?>