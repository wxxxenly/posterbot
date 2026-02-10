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