<?php
function send_email($to, $subject, $body) {
    require 'config.php';

    // Используем API вместо SMTP
    $api_key = MAILGUN_API_KEY; // ← добавь в config.php
    $domain = 'posterbot.ddns.net';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/$domain/messages");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "api:$api_key");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'from' => 'noreply@' . $domain,
        'to' => $to,
        'subject' => $subject,
        'text' => $body
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Успешный ответ: HTTP 200 и "Queued"
    return $http_code == 200 && strpos($result, '"message":"Queued"') !== false;
}