<?php
require 'auth_check.php';
$is_scheduled = !empty($_GET['scheduled']);
$message = $is_scheduled ? 'Пост добавлен в отложку.' : 'Пост успешно опубликован.';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Результат</title>
    <link rel="stylesheet" href="/assets/style.css">
    <style>
    .page-fade { opacity: 0; transition: opacity 0.4s; min-height: 100vh; }
    .page-fade.loaded { opacity: 1; }
    </style>
</head>
<body>
<div class="page-fade" id="pageFade">
    <main style="max-width: 600px; margin: 60px auto; text-align: center;">
        <h2><?= htmlspecialchars($message) ?></h2>
        <a href="index.php" class="btn btn-secondary">← Назад к публикации</a>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('pageFade').classList.add('loaded');
});
document.querySelectorAll('a[href]').forEach(link => {
    const href = link.getAttribute('href');
    if (href.startsWith('#') || (href.includes('://') && !href.includes(window.location.host)) || link.classList.contains('no-fade')) return;
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('pageFade').classList.remove('loaded');
        setTimeout(() => window.location.href = href, 400);
    });
});
</script>
</body>
</html>