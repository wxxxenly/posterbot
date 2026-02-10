<?php
require 'mailer.php';
if (send_email('wxxxenly@bk.ru', 'Тест', 'Если видите это — всё работает!')) {
    echo "✅ Письмо отправлено!";
} else {
    echo "❌ Ошибка отправки.";
}