<?php
require 'config.php';

$fp = @stream_socket_client('ssl://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 10);
if (!$fp) {
    die("Не удалось подключиться к SMTP: [$errno] $errstr");
} else {
    echo "✅ Подключение к SMTP успешно!";
    fclose($fp);
}