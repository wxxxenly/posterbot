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
    <style>
        :root {
            --bg: #f9fafb;
            --bg-card: #ffffff;
            --text: #333333;
            --text-secondary: #4a5568;
            --border: #edf2f7;
            --accent: #4f46e5;
            --accent-hover: #4338ca;
            --error: #e53e3e;
            --success: #38a169;
            --shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        body.dark-theme {
            --bg: #1a1a1a;
            --bg-card: #2d2d2d;
            --text: #e2e2e2;
            --text-secondary: #a0aec0;
            --border: #444444;
            --shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, sans-serif;
            line-height: 1.6;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .header-left a {
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
        }

        /* === Круглая кнопка темы === */
        .theme-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 18px;
            box-shadow: var(--shadow);
        }

        /* === Трёхколоночная сетка === */
        .main-grid {
            display: grid;
            grid-template-columns: 280px 1fr 280px;
            gap: 24px;
        }

        /* === Боковые панели с прокруткой === */
        .panel {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        .panel-header {
            padding: 16px;
            background: rgba(0,0,0,0.03);
            font-weight: 600;
            font-size: 16px;
            color: var(--text);
        }
        .panel-body {
            padding: 16px;
            overflow-y: auto;
            max-height: 60vh;
            flex: 1;
        }
        .panel-empty {
            text-align: center;
            color: var(--text-secondary);
            padding: 24px 0;
            font-style: italic;
        }

        /* === Пост в панели === */
        .post-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        .post-item:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }
        .post-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
            cursor: pointer;
        }
        .post-meta {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        .post-text {
            font-size: 14px;
            color: var(--text);
            margin: 8px 0;
            white-space: pre-wrap;
        }

        /* === Галерея изображений в посте === */
        .post-gallery {
            display: flex;
            gap: 6px;
            overflow-x: auto;
            margin-top: 10px;
            padding: 4px 0;
            scrollbar-width: thin;
        }
        .post-gallery::-webkit-scrollbar {
            height: 5px;
        }
        .post-gallery img {
            height: 60px;
            min-width: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .post-tags {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }
        .tag {
            background: rgba(76, 117, 168, 0.15);
            color: #4C75A8;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .tag.tg {
            background: rgba(0, 136, 204, 0.15);
            color: #0088cc;
        }
        .tag.published {
            background: rgba(56, 161, 105, 0.15);
            color: var(--success);
        }

        /* === Центральная форма === */
        .form-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        h2 {
            margin: 0 0 20px 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }
        textarea {
            width: 100%;
            min-height: 120px;
            padding: 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text);
            font-size: 16px;
            resize: vertical;
            margin-bottom: 16px;
        }
        textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        /* === Кнопки площадок с иконками === */
        .platforms {
            display: flex;
            gap: 20px;
            margin: 24px 0;
            flex-wrap: wrap;
            justify-content: center;
        }
        .platform-item {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            padding: 16px 12px;
            border-radius: 16px;
            background: var(--bg-card);
            cursor: pointer;
            transition: all 0.25s ease;
            min-width: 96px;
            border: 2px solid transparent;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .platform-icon-wrapper {
            position: relative;
            width: 48px;
            height: 48px;
        }
        .platform-icon {
            width: 100%;
            height: 100%;
            display: block;
        }
        .check-mark {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: #4C75A8;
            color: white;
            font-size: 12px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s ease;
            border-radius: 50%;
        }
        .platform-item[data-platform="vk"] .check-mark { background: #4C75A8; }
        .platform-item[data-platform="tg"] .check-mark { background: #0088cc; }
        .platform-item input[type="checkbox"]:checked ~ .platform-icon-wrapper .check-mark {
            opacity: 1;
            transform: scale(1);
        }
        .platform-item input[type="checkbox"]:checked ~ .platform-label {
            font-weight: 600;
        }
        .platform-item[data-platform="vk"] input[type="checkbox"]:checked ~ .platform-icon-wrapper {
            box-shadow: 0 0 0 4px rgba(76, 117, 168, 0.3);
        }
        .platform-item[data-platform="tg"] input[type="checkbox"]:checked ~ .platform-icon-wrapper {
            box-shadow: 0 0 0 4px rgba(0, 136, 204, 0.3);
        }
        .platform-label {
            font-size: 14px;
            color: var(--text-secondary);
            text-align: center;
        }
        .platform-item input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        /* === Загрузка изображений === */
        .upload-area {
            margin: 20px 0;
            border: 2px dashed var(--border);
            border-radius: 12px;
            background: var(--bg-card);
            padding: 32px 20px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: var(--accent);
            background: rgba(79, 70, 229, 0.05);
        }
        .upload-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }
        .upload-placeholder svg {
            width: 48px;
            height: 48px;
            color: var(--text-secondary);
        }
        .upload-placeholder p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
        }
        .upload-btn {
            color: var(--accent);
            text-decoration: underline;
            cursor: pointer;
            font-weight: 600;
        }

        /* === Галерея изображений === */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 12px;
        }
        .gallery-item {
            position: relative;
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            background: var(--bg-card);
            border: 1px solid var(--border);
        }
        .gallery-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-item .remove-btn {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 20px;
            height: 20px;
            background: #e53e3e;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .gallery-item:hover .remove-btn {
            opacity: 1;
        }

        /* === Дата === */
        .datetime-group {
            margin: 20px 0;
        }
        .datetime-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text);
        }
        .datetime-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text);
            font-size: 16px;
        }
        .datetime-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }

        /* === Кнопки отправки === */
        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .btn-primary {
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        /* === Уведомления === */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert-success {
            background: #f0fff4;
            color: var(--success);
            border: 1px solid #b2eab9;
        }
        .alert-error {
            background: #fef2f2;
            color: var(--error);
            border: 1px solid #f4a3a3;
        }

        /* === Предпросмотр в реальном времени === */
        .preview-section {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .preview {
            background: var(--bg-card);
            padding: 16px;
            border-radius: 10px;
            margin: 16px 0;
            border: 1px solid var(--border);
        }
        .preview h4 {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 16px;
            color: var(--text);
        }
        .preview-platform-icon {
            width: 18px;
            height: 18px;
            vertical-align: middle;
        }
        #vk-text, #tg-text {
            white-space: pre-wrap;
            line-height: 1.5;
        }

        /* === Галерея в предпросмотре === */
        .preview-gallery {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 8px 0;
            margin-top: 12px;
            scrollbar-width: thin;
        }
        .preview-gallery::-webkit-scrollbar {
            height: 6px;
        }
        .preview-gallery::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }
        .preview-gallery img {
            height: 80px;
            min-width: 80px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .preview-gallery img:hover {
            transform: scale(1.05);
        }

        /* === Футер === */
        footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--text-secondary);
        }
        footer a {
            display: inline-block;
            padding: 10px 20px;
            background: var(--error);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        /* === Адаптивность === */
        @media (max-width: 900px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            .panel, .form-card {
                max-width: 100%;
            }
        }
        @media (max-width: 600px) {
            body { padding: 12px; }
            .platforms { gap: 12px; }
            .platform-item { min-width: 80px; padding: 12px 8px; }
        }
    </style>
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
</div>

<script>
// === Переключение темы ===
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

// === Множественная загрузка изображений ===
const fileInput = document.getElementById('fileInput');
const galleryGrid = document.getElementById('galleryGrid');
const galleryPreview = document.getElementById('galleryPreview');
const uploadArea = document.getElementById('uploadArea');
const uploadBtn = document.querySelector('.upload-btn');
const clearGalleryBtn = document.getElementById('clearGallery');

let uploadedFiles = [];

uploadBtn.addEventListener('click', () => fileInput.click());

fileInput.addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    if (files.length === 0) return;

    if (uploadedFiles.length + files.length > 10) {
        alert('Можно загрузить не более 10 изображений.');
        return;
    }

    files.forEach(file => {
        if (!file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function(event) {
            uploadedFiles.push({
                file,
                url: event.target.result
            });
            renderGallery();
        };
        reader.readAsDataURL(file);
    });

    fileInput.value = '';
});

function renderGallery() {
    galleryGrid.innerHTML = '';
    uploadedFiles.forEach((item, index) => {
        const itemEl = document.createElement('div');
        itemEl.className = 'gallery-item';
        itemEl.innerHTML = `
            <img src="${item.url}" alt="Предпросмотр">
            <div class="remove-btn" data-index="${index}">×</div>
        `;
        galleryGrid.appendChild(itemEl);

        itemEl.querySelector('.remove-btn').addEventListener('click', () => {
            uploadedFiles.splice(index, 1);
            renderGallery();
        });
    });

    galleryPreview.style.display = uploadedFiles.length > 0 ? 'block' : 'none';
    uploadArea.style.display = uploadedFiles.length >= 10 ? 'none' : 'block';
}

clearGalleryBtn.addEventListener('click', () => {
    uploadedFiles = [];
    renderGallery();
});

// === Предпросмотр в реальном времени ===
function updateLivePreview() {
    const text = document.querySelector('textarea[name="text"]').value.trim();
    const vkChecked = document.querySelector('input[name="to_vk"]').checked;
    const tgChecked = document.querySelector('input[name="to_tg"]').checked;

    // VK
    const vkTextEl = document.getElementById('vk-text');
    const vkGallery = document.getElementById('vk-gallery');
    if (vkChecked) {
        vkTextEl.textContent = text || (uploadedFiles.length ? 'Текст поста' : '');
        renderPreviewGallery(vkGallery, uploadedFiles);
    } else {
        vkTextEl.textContent = '';
        vkGallery.innerHTML = '';
    }

    // TG
    const tgTextEl = document.getElementById('tg-text');
    const tgGallery = document.getElementById('tg-gallery');
    if (tgChecked) {
        tgTextEl.textContent = text || (uploadedFiles.length ? 'Текст поста' : '');
        renderPreviewGallery(tgGallery, uploadedFiles);
    } else {
        tgTextEl.textContent = '';
        tgGallery.innerHTML = '';
    }
}

// Отрисовка галереи в предпросмотре
function renderPreviewGallery(container, files) {
    container.innerHTML = '';
    if (files.length === 0) return;

    files.forEach(item => {
        const img = document.createElement('img');
        img.src = item.url;
        img.alt = 'Предпросмотр';
        img.loading = 'lazy';
        container.appendChild(img);
    });
}

// Слушатели
document.querySelector('textarea[name="text"]').addEventListener('input', updateLivePreview);
document.querySelectorAll('.platform-item input[type="checkbox"]').forEach(cb => {
    cb.addEventListener('change', updateLivePreview);
});

// === Валидация даты ===
document.getElementById('postForm').addEventListener('submit', function(e) {
    const action = e.submitter.value;
    if (action === 'schedule') {
        const dt = document.querySelector('[name="scheduled_at"]').value;
        if (!dt || new Date(dt) <= new Date()) {
            e.preventDefault();
            alert('Выберите дату в будущем!');
            return false;
        }
    }
});
</script>
</body>
</html>