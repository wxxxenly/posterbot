<?php
require 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Находим посты, которые пора публиковать
$stmt = $pdo->prepare("
    SELECT p.*, u.vk_access_token, u.vk_group_id, u.tg_bot_token, u.tg_chat_id
    FROM posts p
    JOIN users u ON p.user_id = u.id
    WHERE p.scheduled_at <= NOW()
      AND p.status = 'scheduled'
    LIMIT 10
");
$stmt->execute();
$posts = $stmt->fetchAll();

foreach ($posts as $post) {
    $vk_ok = false;
    $tg_ok = false;

    // Публикация в VK
    if ($post['to_vk'] && $post['vk_access_token'] && $post['vk_group_id']) {
        $vk_ok = publishToVK(
            $post['text'],
            $post['image_path'],
            $post['vk_access_token'],
            $post['vk_group_id']
        );
    }

    // Публикация в Telegram
    if ($post['to_tg'] && $post['tg_bot_token'] && $post['tg_chat_id']) {
        $tg_ok = publishToTelegram(
            $post['text'],
            $post['image_path'],
            $post['tg_bot_token'],
            $post['tg_chat_id']
        );
    }

    // Обновляем статус и флаги
    $update = $pdo->prepare("
        UPDATE posts 
        SET vk_posted = ?, tg_posted = ?, status = 'published' 
        WHERE id = ?
    ");
    $update->execute([(int)(bool)$vk_ok, (int)(bool)$tg_ok, $post['id']]);
}

// === Функции публикации ===
function publishToVK($text, $image_path, $access_token, $group_id) {
    $url = "https://api.vk.com/method/";
    $attachment = '';

    if ($image_path && file_exists($image_path)) {
        $server_url = $url . "photos.getWallUploadServer?access_token=$access_token&v=5.199&group_id=$group_id";
        $server_resp = file_get_contents($server_url);
        $server_data = json_decode($server_resp, true);
        if (!$server_data || !isset($server_data['response']['upload_url'])) return false;

        $ch = curl_init($server_data['response']['upload_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['photo' => new CURLFile($image_path)]);
        $upload_result = curl_exec($ch);
        curl_close($ch);
        $photo_data = json_decode($upload_result, true);
        if (!$photo_data || !isset($photo_data['photo'])) return false;

        $save_url = $url . "photos.saveWallPhoto?access_token=$access_token&v=5.199&group_id=$group_id&photo={$photo_data['photo']}&server={$photo_data['server']}&hash={$photo_data['hash']}";
        $save_resp = file_get_contents($save_url);
        $saved = json_decode($save_resp, true);
        if ($saved && isset($saved['response'][0]['id'])) {
            $attachment = "photo-{$group_id}_{$saved['response'][0]['id']}";
        }
    }

    $params = [
        'access_token' => $access_token,
        'v' => '5.199',
        'owner_id' => "-$group_id",
        'message' => $text,
        'from_group' => 1
    ];
    if ($attachment) $params['attachments'] = $attachment;

    $post_url = $url . "wall.post?" . http_build_query($params);
    $post_resp = file_get_contents($post_url);
    $result = json_decode($post_resp, true);
    return $result && !isset($result['error']);
}

function publishToTelegram($text, $image_path, $bot_token, $chat_id) {
    $url = "https://api.telegram.org/bot$bot_token/";
    if ($image_path && file_exists($image_path)) {
        $ch = curl_init($url . "sendPhoto");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $chat_id,
            'caption' => $text,
            'photo' => new CURLFile($image_path)
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false;
    } else {
        $msg_url = $url . "sendMessage?chat_id=" . urlencode($chat_id) . "&text=" . urlencode($text);
        $result = file_get_contents($msg_url);
        return $result !== false;
    }
}