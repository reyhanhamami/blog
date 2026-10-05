import { initMediaGallery, safeImageUrl, uploadMedia } from './media-library';
import './app.js';

function initBrandingPreviews() {
    document.querySelectorAll('[data-branding-upload]').forEach(input => {
        if (input.dataset.previewBound) return;
        input.dataset.previewBound = '1';
        let objectUrl;
        input.addEventListener('change', () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            const image = document.querySelector(`[data-branding-preview="${input.dataset.brandingUpload}"]`);
            if (!image) return;
            const file = input.files?.[0];
            image.src = file ? (objectUrl = URL.createObjectURL(file)) : image.dataset.currentSrc;
            const largeImage = document.querySelector(`[data-branding-preview-large="${input.dataset.brandingUpload}"]`);
            if (largeImage) largeImage.src = image.src;
            document.querySelector(`[data-branding-selected="${input.dataset.brandingUpload}"]`)?.replaceChildren(document.createTextNode(file?.name || 'Belum ada file baru'));
        });
    });
}

function initHomepageHeroPicker() {
    const picker = document.querySelector('[data-home-hero-picker]');
    const field = document.querySelector('[data-home-hero-image]');
    if (!picker || !field || picker.dataset.bound) return;
    picker.dataset.bound = '1';
    const get = selector => picker.querySelector(selector);
    const value = field.querySelector('[data-home-image-value]');
    const preview = field.querySelector('[data-home-image-preview]');
    const empty = field.querySelector('[data-home-image-empty]');
    const choose = field.querySelector('[data-home-image-choose]');
    const clear = field.querySelector('[data-home-image-clear]');
    const alt = document.querySelector('[name="home_hero_image_alt"]');
    let tab = 'url';
    let selectedMedia = null;
    let objectUrl = null;
    const gallery = initMediaGallery(get('[data-media-gallery]'), item => {
        selectedMedia = item;
        showPreview(item.url);
    });
    function error(message = '') {
        const node = get('[data-home-picker-error]');
        node.textContent = message;
        node.hidden = !message;
    }
    function releaseObjectUrl() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }
    function showPreview(url) {
        const image = get('[data-home-picker-preview]');
        image.hidden = !url;
        get('[data-home-picker-empty]').hidden = !!url;
        if (url) image.src = url;
        else image.removeAttribute('src');
    }
    function setImage(url, imageAlt = '') {
        const previousUrl = value.value;
        value.value = url;
        preview.hidden = !url;
        empty.hidden = !!url;
        if (url) preview.src = url;
        else preview.removeAttribute('src');
        clear.hidden = !url;
        choose.textContent = url ? 'Ganti gambar' : 'Pilih gambar';
        if (imageAlt) alt.value = imageAlt;
        else if (url !== previousUrl) alt.value = '';
    }
    function chooseTab(next) {
        tab = next;
        picker.querySelectorAll('[data-home-picker-tab]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.homePickerTab === next)));
        picker.querySelectorAll('[data-home-picker-pane]').forEach(pane => { pane.hidden = pane.dataset.homePickerPane !== next; });
        error();
        if (next === 'library') gallery.load();
    }
    choose.addEventListener('click', () => {
        selectedMedia = null;
        releaseObjectUrl();
        if (get('[data-home-picker-file]')) get('[data-home-picker-file]').value = '';
        if (get('[data-home-picker-alt]')) get('[data-home-picker-alt]').value = alt.value;
        get('[data-home-picker-url]').value = value.value;
        showPreview(value.value);
        chooseTab(value.value ? 'url' : get('[data-home-picker-tab]')?.dataset.homePickerTab || 'url');
        picker.showModal();
    });
    clear.addEventListener('click', () => setImage(''));
    picker.querySelectorAll('[data-home-picker-tab]').forEach(button => button.addEventListener('click', () => {
        selectedMedia = null;
        chooseTab(button.dataset.homePickerTab);
    }));
    get('[data-home-picker-file]')?.addEventListener('change', event => {
        releaseObjectUrl();
        selectedMedia = null;
        const file = event.target.files?.[0];
        if (!file) { showPreview(''); return; }
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            event.target.value = '';
            error('Gunakan JPG, PNG, WebP, atau GIF maksimal 5 MB.');
            return;
        }
        objectUrl = URL.createObjectURL(file);
        showPreview(objectUrl);
        error();
    });
    get('[data-home-picker-url-preview]').addEventListener('click', () => {
        const url = get('[data-home-picker-url]').value.trim();
        if (!safeImageUrl(url)) { error('URL harus menggunakan HTTP atau HTTPS.'); return; }
        showPreview(url);
        error();
    });
    get('[data-home-picker-preview]').addEventListener('error', () => error('Preview tidak dapat dimuat. Periksa URL atau akses gambar.'));
    get('[data-home-picker-cancel]').addEventListener('click', () => picker.close());
    picker.addEventListener('close', releaseObjectUrl);
    get('[data-home-picker-apply]').addEventListener('click', async () => {
        const button = get('[data-home-picker-apply]');
        let media = selectedMedia;
        error();
        if (tab === 'upload') {
            const file = get('[data-home-picker-file]')?.files?.[0];
            const imageAlt = get('[data-home-picker-alt]')?.value.trim();
            if (!file || !imageAlt) { error('Pilih file dan isi alt gambar sebelum upload.'); return; }
            button.disabled = true;
            button.textContent = 'Mengunggah...';
            try { media = await uploadMedia(picker.dataset.mediaUploadUrl, file, imageAlt); }
            catch (exception) { error(exception.message); return; }
            finally { button.disabled = false; button.textContent = 'Pilih gambar'; }
        } else if (tab === 'url') media = { url: get('[data-home-picker-url]').value.trim() };
        if (!media?.url || !safeImageUrl(media.url)) { error('Pilih gambar atau masukkan URL HTTP/HTTPS yang valid.'); return; }
        setImage(media.url, media.alt);
        picker.close();
    });
}

function initAdminSettings() {
    initBrandingPreviews();
    initHomepageHeroPicker();
}

document.addEventListener('DOMContentLoaded', initAdminSettings);
document.addEventListener('livewire:navigated', initAdminSettings);
