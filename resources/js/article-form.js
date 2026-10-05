import { initMediaGallery, safeImageUrl, uploadMedia } from './media-library';

const slugify = value => value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');

export function initArticleForm() {
    const form = document.querySelector('[data-post-form]');
    if (!form) return null;
    const abort = new AbortController();
    const listen = (target, name, callback) => target?.addEventListener(name, callback, { signal: abort.signal });
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const taxonomy = document.querySelector('[data-taxonomy-dialog]');
    const taxonomyGet = selector => taxonomy.querySelector(selector);
    const taxonomyLabels = { categories: 'Kategori', tags: 'Tag', topics: 'Topik' };
    const taxonomyRoutes = {
        categories: form.querySelector('[data-taxonomy-select="categories"]')?.dataset.storeUrl,
        tags: form.querySelector('[data-taxonomy-select="tags"]')?.dataset.storeUrl,
        topics: form.querySelector('[data-taxonomy-select="topics"]')?.dataset.storeUrl,
    };
    let module;
    let manualSlug = false;
    let duplicateItem = null;
    function taxonomyError(selector, message) { const node = taxonomyGet(selector); node.textContent = message || ''; node.hidden = !message; }
    function chooseTaxonomy(target, item) {
        const select = form.querySelector(`[data-taxonomy-select="${target}"]`);
        if (!select) return;
        if (select.tomselect) {
            select.tomselect.addOption({ value: String(item.id), text: item.name });
            select.tomselect.refreshOptions(false);
            if (select.multiple) select.tomselect.addItem(String(item.id)); else select.tomselect.setValue(String(item.id));
        } else {
            if (![...select.options].some(option => option.value === String(item.id))) select.add(new Option(item.name, item.id));
            select.options[[...select.options].findIndex(option => option.value === String(item.id))].selected = true;
        }
        taxonomy.close();
        const feedback = form.querySelector('[data-taxonomy-success]');
        feedback.textContent = `${taxonomyLabels[target]} ${item.name} dipilih.`;
        feedback.hidden = false;
    }
    form.querySelectorAll('[data-taxonomy-open]').forEach(button => listen(button, 'click', () => {
        module = button.dataset.taxonomyOpen;
        taxonomyGet('[data-taxonomy-title]').textContent = `Buat ${taxonomyLabels[module]} baru`;
        taxonomyGet('[data-taxonomy-name]').value = '';
        taxonomyGet('[data-taxonomy-slug]').value = '';
        taxonomyGet('[data-taxonomy-slug-wrap]').hidden = module === 'tags';
        ['[data-taxonomy-name-error]', '[data-taxonomy-slug-error]', '[data-taxonomy-error]'].forEach(selector => taxonomyError(selector, ''));
        duplicateItem = null;
        taxonomyGet('[data-taxonomy-save]').textContent = 'Buat & pilih';
        manualSlug = false;
        taxonomy.showModal();
        taxonomyGet('[data-taxonomy-name]').focus();
    }));
    listen(taxonomyGet('[data-taxonomy-name]'), 'input', event => {
        duplicateItem = null;
        taxonomyGet('[data-taxonomy-save]').textContent = 'Buat & pilih';
        if (!manualSlug) taxonomyGet('[data-taxonomy-slug]').value = slugify(event.target.value);
        taxonomyError('[data-taxonomy-name-error]', '');
    });
    listen(taxonomyGet('[data-taxonomy-slug]'), 'input', () => { manualSlug = true; taxonomyError('[data-taxonomy-slug-error]', ''); });
    for (const selector of ['[data-taxonomy-name]', '[data-taxonomy-slug]']) {
        listen(taxonomyGet(selector), 'keydown', event => {
            if (event.key === 'Enter') { event.preventDefault(); taxonomyGet('[data-taxonomy-save]').click(); }
        });
    }
    listen(taxonomyGet('[data-taxonomy-cancel]'), 'click', () => taxonomy.close());
    listen(taxonomyGet('[data-taxonomy-save]'), 'click', async () => {
        if (duplicateItem) { chooseTaxonomy(module, duplicateItem); duplicateItem = null; taxonomyGet('[data-taxonomy-save]').textContent = 'Buat & pilih'; return; }
        const name = taxonomyGet('[data-taxonomy-name]').value.trim();
        if (!name) { taxonomyError('[data-taxonomy-name-error]', 'Nama wajib diisi.'); return; }
        const button = taxonomyGet('[data-taxonomy-save]');
        button.disabled = true; button.textContent = 'Menyimpan...';
        try {
            const payload = { name, slug: taxonomyGet('[data-taxonomy-slug]').value.trim() };
            const response = await fetch(taxonomyRoutes[module], { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload) });
            const result = await response.json();
            if (response.status === 409 && result.existing) {
                taxonomyError('[data-taxonomy-name-error]', `${taxonomyLabels[module]} "${result.existing.name}" sudah tersedia. Gunakan pilihan tersebut.`);
                duplicateItem = result.existing;
                button.textContent = 'Gunakan yang tersedia';
                return;
            }
            if (!response.ok) {
                taxonomyError('[data-taxonomy-name-error]', result.errors?.name?.[0]);
                taxonomyError('[data-taxonomy-slug-error]', result.errors?.slug?.[0]);
                taxonomyError('[data-taxonomy-error]', result.message || 'Gagal membuat pilihan.');
                return;
            }
            chooseTaxonomy(module, result);
        } catch { taxonomyError('[data-taxonomy-error]', 'Koneksi gagal. Coba lagi.'); }
        finally { button.disabled = false; if (!duplicateItem) button.textContent = 'Buat & pilih'; }
    });

    const picker = document.querySelector('[data-article-media-picker]');
    const pickerGet = selector => picker.querySelector(selector);
    const fields = Object.fromEntries(['featured', 'og'].map(kind => [kind, form.querySelector(`[data-article-image-field="${kind}"]`)]));
    const imageValue = kind => fields[kind].querySelector('[data-image-value]');
    const ogControl = form.querySelector('[data-og-control]');
    let ogCustomValue = imageValue('og').value;
    let currentKind;
    let currentTab;
    let selectedMedia;
    let objectUrl;
    const gallery = initMediaGallery(pickerGet('[data-media-gallery]'), item => {
        selectedMedia = item;
        pickerGet('[data-picker-alt]') && (pickerGet('[data-picker-alt]').value = item.alt || '');
        showPickerPreview(item.url, item);
    });
    function pickerError(message) { const node = pickerGet('[data-picker-error]'); node.textContent = message || ''; node.hidden = !message; }
    function releaseObjectUrl() { if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = null; }
    function showPickerPreview(url, media = {}) {
        const image = pickerGet('[data-picker-preview]');
        image.hidden = !url; pickerGet('[data-picker-empty]').hidden = !!url;
        if (url) image.src = url; else image.removeAttribute('src');
        const dimensions = pickerGet('[data-picker-dimensions]');
        dimensions.textContent = media.width && media.height ? `${media.width} × ${media.height} px` : '';
        dimensions.hidden = !dimensions.textContent;
        pickerGet('[data-picker-ratio-warning]').hidden = true;
    }
    function setImage(kind, url, media = {}) {
        imageValue(kind).value = url;
        const field = fields[kind];
        const image = field.querySelector('[data-image-preview]');
        image.hidden = !url;
        if (url) image.src = url; else image.removeAttribute('src');
        field.querySelector('[data-image-empty]').hidden = !!url;
        field.querySelector('[data-image-clear]').hidden = !url;
        field.querySelector('[data-image-choose]').textContent = url ? 'Ganti gambar' : 'Pilih gambar';
        if (kind === 'featured') {
            if (media.alt) form.querySelector('[name="featured_image_alt"]').value = media.alt;
            const ogImage = ogControl.querySelector('[data-og-featured-image]');
            ogImage.hidden = !url;
            if (url) ogImage.src = url; else ogImage.removeAttribute('src');
            ogControl.querySelector('[data-og-featured-empty]').hidden = !!url;
        } else ogCustomValue = url;
    }
    function chooseTab(tab) {
        currentTab = tab;
        picker.querySelectorAll('[data-picker-tab]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.pickerTab === tab)));
        picker.querySelectorAll('[data-picker-pane]').forEach(pane => pane.hidden = pane.dataset.pickerPane !== tab);
        pickerError('');
        if (tab === 'library') gallery.load();
    }
    form.querySelectorAll('[data-image-choose]').forEach(button => listen(button, 'click', () => {
        currentKind = button.closest('[data-article-image-field]').dataset.articleImageField;
        selectedMedia = null; releaseObjectUrl();
        pickerGet('[data-picker-file]') && (pickerGet('[data-picker-file]').value = '');
        pickerGet('[data-picker-url]').value = imageValue(currentKind).value;
        pickerGet('[data-picker-alt]') && (pickerGet('[data-picker-alt]').value = currentKind === 'featured' ? form.querySelector('[name="featured_image_alt"]').value : '');
        pickerGet('[data-picker-title]').textContent = currentKind === 'featured' ? 'Gambar artikel' : 'OG image';
        showPickerPreview(imageValue(currentKind).value);
        chooseTab(imageValue(currentKind).value ? 'url' : picker.querySelector('[data-picker-tab]')?.dataset.pickerTab || 'url');
        picker.showModal();
    }));
    form.querySelectorAll('[data-image-clear]').forEach(button => listen(button, 'click', () => {
        const kind = button.closest('[data-article-image-field]').dataset.articleImageField;
        setImage(kind, '');
        if (kind === 'featured') form.querySelector('[name="featured_image_alt"]').value = '';
    }));
    picker.querySelectorAll('[data-picker-tab]').forEach(button => listen(button, 'click', () => chooseTab(button.dataset.pickerTab)));
    listen(pickerGet('[data-picker-file]'), 'change', event => {
        releaseObjectUrl(); selectedMedia = null;
        const file = event.target.files?.[0];
        if (!file) { showPickerPreview(''); return; }
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) { event.target.value = ''; pickerError('Gunakan JPG, PNG, WebP, atau GIF maksimal 5 MB.'); return; }
        pickerError(''); objectUrl = URL.createObjectURL(file); showPickerPreview(objectUrl);
    });
    const dropzone = pickerGet('[data-picker-dropzone]');
    listen(dropzone, 'dragover', event => { event.preventDefault(); dropzone.classList.add('is-dragging'); });
    listen(dropzone, 'dragleave', () => dropzone.classList.remove('is-dragging'));
    listen(dropzone, 'drop', event => {
        event.preventDefault(); dropzone.classList.remove('is-dragging');
        const file = event.dataTransfer.files?.[0]; if (!file) return;
        const transfer = new DataTransfer(); transfer.items.add(file);
        const input = pickerGet('[data-picker-file]'); input.files = transfer.files; input.dispatchEvent(new Event('change'));
    });
    listen(pickerGet('[data-picker-url-preview]'), 'click', () => {
        const url = pickerGet('[data-picker-url]').value.trim();
        if (!safeImageUrl(url)) { pickerError('URL harus diawali http:// atau https://.'); return; }
        pickerError(''); showPickerPreview(url);
    });
    listen(pickerGet('[data-picker-preview]'), 'load', event => {
        const { naturalWidth: width, naturalHeight: height } = event.target;
        const dimensions = pickerGet('[data-picker-dimensions]');
        dimensions.textContent = `${width} × ${height} px`;
        dimensions.hidden = false;
        pickerGet('[data-picker-ratio-warning]').hidden = currentKind !== 'og' || !height || (width / height >= 1.5 && width / height <= 2.4);
        pickerError('');
    });
    listen(pickerGet('[data-picker-preview]'), 'error', () => pickerError('Preview tidak dapat dimuat. Periksa URL atau izin akses gambar.'));
    listen(pickerGet('[data-picker-cancel]'), 'click', () => picker.close());
    listen(picker, 'close', releaseObjectUrl);
    listen(pickerGet('[data-picker-apply]'), 'click', async () => {
        const button = pickerGet('[data-picker-apply]');
        pickerError('');
        let media = selectedMedia;
        if (currentTab === 'upload') {
            const file = pickerGet('[data-picker-file]')?.files?.[0];
            const alt = pickerGet('[data-picker-alt]')?.value.trim();
            if (!file || !alt) { pickerError('Pilih file dan isi alt text sebelum upload.'); return; }
            button.disabled = true; button.textContent = 'Mengunggah...';
            try { media = await uploadMedia(picker.dataset.mediaUploadUrl, file, alt); }
            catch (error) { pickerError(error.message); return; }
            finally { button.disabled = false; button.textContent = 'Pilih gambar'; }
        } else if (currentTab === 'url') media = { url: pickerGet('[data-picker-url]').value.trim() };
        if (!media?.url || !safeImageUrl(media.url)) { pickerError('Pilih gambar atau masukkan URL HTTP/HTTPS yang valid.'); return; }
        setImage(currentKind, media.url, media);
        picker.close();
    });

    const ogMode = () => form.querySelector('[name="og_image_mode"]:checked')?.value || 'featured';
    function syncOgMode() {
        const custom = ogMode() === 'custom';
        ogControl.querySelector('[data-og-custom]').hidden = !custom;
        ogControl.querySelector('[data-og-featured-preview]').hidden = custom;
        imageValue('og').value = custom ? ogCustomValue : '';
    }
    form.querySelectorAll('[name="og_image_mode"]').forEach(input => listen(input, 'change', syncOgMode));
    listen(form, 'submit', syncOgMode);
    syncOgMode();

    return { destroy() { abort.abort(); gallery.destroy(); releaseObjectUrl(); if (taxonomy.open) taxonomy.close(); if (picker.open) picker.close(); } };
}
