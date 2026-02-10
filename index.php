<?php
require 'auth_check.php';
require 'config.php';

$pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

// Функция получения всех изображений поста
function getPostImages($image_path, $additional_images) {
    $images = [];
    if ($image_path) {
        $images[] = $image_path;
    }
    if ($additional_images) {
        $extra = json_decode($additional_images, true);
        if (is_array($extra)) {
            $images = array_merge($images, $extra);
        }
    }
    return $images;
}

// Отложенные посты
$stmt = $pdo->prepare("
    SELECT id, text, image_path, additional_images, scheduled_at, to_vk, to_tg, created_at 
    FROM posts 
    WHERE user_id = ? AND status = 'scheduled' 
    ORDER BY scheduled_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$scheduled_posts = $stmt->fetchAll();

// История
$stmt = $pdo->prepare("
    SELECT id, text, image_path, additional_images, created_at, vk_posted, tg_posted
    FROM posts 
    WHERE user_id = ? AND status = 'published' 
    ORDER BY created_at DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$published_posts = $stmt->fetchAll();

$msg = $_GET['msg'] ?? null;
$error = $_GET['error'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Постинг</title>
    <link rel="stylesheet" href="/assets/style_index.css">
</head>
<body>

<div class="container">
    <header>
        <div class="header-left">
            <a href="profile.php">👤 Профиль</a>
        </div>
        <div class="theme-btn" id="themeToggle">🌙</div>
    </header>

    <!-- Уведомления -->
    <?php if ($msg === 'published'): ?>
        <div class="alert alert-success">✅ Пост опубликован!</div>
    <?php elseif ($msg === 'scheduled'): ?>
        <div class="alert alert-success">✅ Пост добавлен в отложку!</div>
    <?php elseif ($error): ?>
        <div class="alert alert-error">
            <?php
            switch ($error) {
                case 'invalid_date': echo '❌ Дата должна быть в будущем.'; break;
                case 'invalid_image': echo '❌ Неверный формат изображения.'; break;
                case 'empty_post': echo '❌ Пост не может быть пустым — добавьте текст или фото.'; break;
                default: echo '❌ Ошибка.';
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Трёхколоночная сетка -->
    <div class="main-grid">
        <!-- Левая панель: отложенные -->
        <div class="panel">
            <div class="panel-header">Отложенные (<?= count($scheduled_posts) ?>)</div>
            <div class="panel-body">
                <?php if (empty($scheduled_posts)): ?>
                    <div class="panel-empty">Нет отложенных постов</div>
                <?php else: ?>
                    <?php foreach ($scheduled_posts as $post): ?>
                        <?php
                        $images = getPostImages($post['image_path'], $post['additional_images']);
                        ?>
                        <div class="post-item">
                            <div class="post-title">
                                <?= htmlspecialchars(substr($post['text'], 0, 30) . (strlen($post['text']) > 30 ? '…' : '')) ?>
                            </div>
                            <div class="post-meta">
                                <?= date('d.m.Y H:i', strtotime($post['scheduled_at'])) ?>
                            </div>
                            <?php if (!empty($post['text'])): ?>
                                <div class="post-text"><?= nl2br(htmlspecialchars($post['text'])) ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($images)): ?>
                                <div class="post-gallery">
                                    <?php foreach ($images as $img): ?>
                                        <?php if (file_exists(__DIR__ . '/uploads/' . $img)): ?>
                                            <img src="/uploads/<?= htmlspecialchars($img) ?>" alt="Изображение">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="post-tags">
                                <?php if ($post['to_vk']): ?><span class="tag">VK</span><?php endif; ?>
                                <?php if ($post['to_tg']): ?><span class="tag tg">TG</span><?php endif; ?>
                            </div>
                            <div style="margin-top: 12px;">
                                <a href="delete-scheduled.php?id=<?= $post['id'] ?>" 
                                   style="font-size:12px; color:#ff5252; text-decoration:none;"
                                   onclick="return confirm('Удалить?')">
                                    🗑️ Удалить
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Центр: форма -->
        <div class="form-card">
            <h2>Создать пост</h2>

            <form method="post" action="publish.php" enctype="multipart/form-data" id="postForm">
                <textarea name="text" placeholder="Текст поста (необязательно)"></textarea>

                <div class="platforms">
                    <label class="platform-item" data-platform="vk">
                        <input type="checkbox" name="to_vk" checked>
                        <div class="platform-icon-wrapper">
                            <img src="/assets/vk.svg" alt="ВКонтакте" class="platform-icon">
                            <span class="check-mark">✓</span>
                        </div>
                        <span class="platform-label">ВКонтакте</span>
                    </label>
                    <label class="platform-item" data-platform="tg">
                        <input type="checkbox" name="to_tg">
                        <div class="platform-icon-wrapper">
                            <img src="/assets/telegram.svg" alt="Telegram" class="platform-icon">
                            <span class="check-mark">✓</span>
                        </div>
                        <span class="platform-label">Telegram</span>
                    </label>
                </div>

                <!-- Множественная загрузка изображений -->
                <div class="upload-area" id="uploadArea">
                    <div class="upload-placeholder">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3v18M3 12h18"/>
                        </svg>
                        <p>Перетащите фото сюда<br>или <span class="upload-btn">выберите файлы</span></p>
                        <input type="file" name="images[]" accept="image/*" multiple id="fileInput" style="display:none;">
                    </div>

                    <div class="gallery-preview" id="galleryPreview" style="display: none; margin-top: 16px;">
                        <div class="gallery-grid" id="galleryGrid"></div>
                        <button type="button" class="btn btn-danger" id="clearGallery" style="margin-top: 12px; width: 100%;">
                            🗑️ Очистить все изображения
                        </button>
                    </div>
                </div>

                <div class="datetime-group">
                    <label>Дата публикации (для отложки):</label>
                    <input type="datetime-local" name="scheduled_at" class="datetime-input">
                </div>

                <div class="btn-group">
                    <button type="submit" name="action" value="publish" class="btn btn-primary">
                        🚀 Опубликовать сейчас
                    </button>
                    <button type="submit" name="action" value="schedule" class="btn btn-secondary">
                        ➕ Отложить
                    </button>
                </div>
            </form>

            <!-- Предпросмотр в реальном времени -->
            <div class="preview-section">
                <h3>Предпросмотр публикации</h3>
                
                <!-- VK -->
                <div class="preview" id="preview-vk">
                    <h4><img src="/assets/vk.svg" alt="ВКонтакте" class="preview-platform-icon"> ВКонтакте</h4>
                    <div id="vk-text"></div>
                    <div class="preview-gallery" id="vk-gallery"></div>
                </div>
                
                <!-- Telegram -->
                <div class="preview" id="preview-tg">
                    <h4><img src="/assets/telegram.svg" alt="Telegram" class="preview-platform-icon"> Telegram</h4>
                    <div id="tg-text"></div>
                    <div class="preview-gallery" id="tg-gallery"></div>
                </div>
            </div>
        </div>

        <!-- Правая панель: история -->
        <div class="panel">
            <div class="panel-header">История (<?= count($published_posts) ?>)</div>
            <div class="panel-body">
                <?php if (empty($published_posts)): ?>
                    <div class="panel-empty">Нет публикаций</div>
                <?php else: ?>
                    <?php foreach ($published_posts as $post): ?>
                        <?php
                        $images = getPostImages($post['image_path'], $post['additional_images']);
                        ?>
                        <div class="post-item">
                            <div class="post-title">
                                <?= htmlspecialchars(substr($post['text'], 0, 30) . (strlen($post['text']) > 30 ? '…' : '')) ?>
                            </div>
                            <div class="post-meta">
                                <?= date('d.m.Y H:i', strtotime($post['created_at'])) ?>
                            </div>
                            <?php if (!empty($post['text'])): ?>
                                <div class="post-text"><?= nl2br(htmlspecialchars($post['text'])) ?></div>
                            <?php endif; ?>
                            
                            <?php if (!empty($images)): ?>
                                <div class="post-gallery">
                                    <?php foreach ($images as $img): ?>
                                        <?php if (file_exists(__DIR__ . '/uploads/' . $img)): ?>
                                            <img src="/uploads/<?= htmlspecialchars($img) ?>" alt="Изображение">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div class="post-tags">
                                <?php if ($post['vk_posted']): ?><span class="tag published">VK ✅</span><?php endif; ?>
                                <?php if ($post['tg_posted']): ?><span class="tag tg published">TG ✅</span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer>
        <a href="logout.php">🚪 Выйти из аккаунта</a>
    </footer>
    <div id="bottom-banner">
    <a href="https://github.com/wxxxenly/posterbot" target="_blank" style="
        color: #444;
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    ">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;">
        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.835 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.605-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.98-.399 3.003-.404 1.023.005 2.055.138 3.003.404 2.291-1.552 3.297-1.23 3.297-1.23.654 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.334-5.479 5.926.43.372.823 1.102.823 2.222v3.293c0 .314.192.687.801.576 4.765-1.589 8.199-6.084 8.199-11.386 0-6.627-5.373-12-12-12z"/>
        </svg>
        GitHub
    </a>
    </div>
</div>
<script src="assets/script_index.js"></script>
</body>
</html>