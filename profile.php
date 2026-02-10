<?php
require 'auth_check.php';
require 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT username, created_at, vk_access_token, vk_group_id, tg_bot_token, tg_chat_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die('Пользователь не найден.');
}

$message = '';

// Обработка VK
if ($_POST['vk_token'] ?? false) {
    $token = trim($_POST['vk_token']);
    $group_id = trim($_POST['vk_group_id']);
    if ($token && $group_id && ctype_digit($group_id)) {
        $stmt = $pdo->prepare("UPDATE users SET vk_access_token = ?, vk_group_id = ? WHERE id = ?");
        $stmt->execute([$token, $group_id, $_SESSION['user_id']]);
        $message = "✅ VK сохранён!";
        $user['vk_access_token'] = $token;
        $user['vk_group_id'] = $group_id;
    } else {
        $message = "❌ Укажите корректный токен и ID паблика.";
    }
}

// Обработка Telegram
if ($_POST['tg_bot_token'] ?? false) {
    $bot_token = trim($_POST['tg_bot_token']);
    $chat_id = trim($_POST['tg_chat_id']);
    if ($bot_token && $chat_id) {
        $stmt = $pdo->prepare("UPDATE users SET tg_bot_token = ?, tg_chat_id = ? WHERE id = ?");
        $stmt->execute([$bot_token, $chat_id, $_SESSION['user_id']]);
        $message = "✅ Telegram сохранён!";
        $user['tg_bot_token'] = $bot_token;
        $user['tg_chat_id'] = $chat_id;
    } else {
        $message = "❌ Укажите токен и ID канала.";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Профиль</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>

    <button id="themeToggle" class="theme-toggle-btn" aria-label="Сменить тему"></button>

    <main>
        <h2>Мой профиль</h2>
        <a href="https://telegra.ph/Kak-polzovatsya-servisom-02-08" target="_blank" class="btn btn-outline" style="margin-bottom: 20px; display: inline-flex; align-items: center; gap: 6px;">
            📖 Как подключить VK и Telegram?
        </a>

        <div style="background: var(--bg-card); padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border);">
            <p><strong>Логин:</strong> <?= htmlspecialchars($user['username']) ?></p>
            <p><strong>Дата регистрации:</strong> <?= $user['created_at'] ? date('d.m.Y H:i', strtotime($user['created_at'])) : '—' ?></p>
        </div>

        <?php if ($message): ?>
            <div class="<?= strpos($message, '✅') !== false ? 'success' : 'error' ?>" style="margin-bottom: 24px;">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- VK -->
        <h3>ВКонтакте</h3>
        <form method="post" style="background: var(--bg-card); padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border);">
            <input type="text" name="vk_token" placeholder="Токен сообщества" value="<?= htmlspecialchars($user['vk_access_token'] ?? '') ?>" style="margin-bottom: 12px;">
            <input type="text" name="vk_group_id" placeholder="ID паблика (цифры)" value="<?= htmlspecialchars($user['vk_group_id'] ?? '') ?>" style="margin-bottom: 16px;">
            <button type="submit" class="btn btn-secondary" style="max-width: none; width: auto;">Сохранить VK</button>
        </form>

        <!-- Telegram -->
        <h3>Telegram</h3>
        <form method="post" style="background: var(--bg-card); padding: 20px; border-radius: 12px; margin-bottom: 24px; border: 1px solid var(--border);">
            <input type="text" name="tg_bot_token" placeholder="Токен бота" value="<?= htmlspecialchars($user['tg_bot_token'] ?? '') ?>" style="margin-bottom: 12px;">
            <input type="text" name="tg_chat_id" placeholder="ID канала (@channel или -100...)" value="<?= htmlspecialchars($user['tg_chat_id'] ?? '') ?>" style="margin-bottom: 16px;">
            <button type="submit" class="btn btn-secondary" style="max-width: none; width: auto;">Сохранить Telegram</button>
        </form>

        <br>
        <a href="index.php" class="btn btn-secondary">← Назад к публикации</a>
    </main>

    <footer class="footer">
        <a href="logout.php" class="btn btn-danger">Выйти из аккаунта</a>
    </footer>

    <script>
    const themeToggle = document.getElementById('themeToggle');
    const body = document.body;

    function updateThemeButton() {
        const isDark = body.classList.contains('dark-theme');
        themeToggle.textContent = isDark ? '☀️' : '🌙';
    }

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-theme');
    }
    updateThemeButton();

    themeToggle.addEventListener('click', () => {
        body.classList.toggle('dark-theme');
        localStorage.setItem('theme', body.classList.contains('dark-theme') ? 'dark' : 'light');
        updateThemeButton();
    });
    </script>
</body>
</html>