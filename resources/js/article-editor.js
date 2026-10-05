import { initMediaGallery, uploadMedia } from './media-library';

const ALLOWED_TAGS = new Set(['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'STRONG', 'EM', 'U', 'S', 'DEL', 'BLOCKQUOTE', 'PRE', 'CODE', 'A', 'IMG', 'FIGURE', 'FIGCAPTION', 'TABLE', 'THEAD', 'TBODY', 'TFOOT', 'TR', 'TH', 'TD', 'BR', 'HR']);
const DISCARD_TAGS = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON', 'SVG', 'MATH', 'TEMPLATE', 'NOSCRIPT']);
const SIZE_VALUES = ['small', 'medium', 'large', 'full'];
const ALIGN_VALUES = ['left', 'center', 'right'];
const LANGUAGES = ['text', 'php', 'javascript', 'typescript', 'html', 'css', 'bash', 'json', 'sql', 'python', 'go'];
const escapeHtml = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
const safeUrl = value => /^(https?:\/\/[^\s<>"']+|\/(?!\/)[^\s<>"']*|#[a-z][\w-]*)$/i.test(value);
const imageClass = (size, align) => `article-image article-image--${SIZE_VALUES.includes(size) ? size : 'medium'} article-image--${ALIGN_VALUES.includes(align) ? align : 'center'}`;

function cleanHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = html;
    const clean = parent => {
        for (const child of [...parent.childNodes]) {
            if (child.nodeType !== Node.ELEMENT_NODE) continue;
            if (DISCARD_TAGS.has(child.tagName)) { child.remove(); continue; }
            if (['B', 'I', 'STRIKE'].includes(child.tagName)) {
                const replacement = document.createElement(({ B: 'strong', I: 'em', STRIKE: 's' })[child.tagName]);
                replacement.append(...child.childNodes);
                child.replaceWith(replacement);
                clean(replacement);
                continue;
            }
            if (child.tagName === 'SPAN') {
                const style = child.getAttribute('style') || '';
                clean(child);
                const bold = /font-weight\s*:\s*(?:bold|[6-9]00)/i.test(style);
                const italic = /font-style\s*:\s*italic/i.test(style);
                if (bold || italic) {
                    let wrapper = document.createElement(bold ? 'strong' : 'em');
                    if (bold && italic) { const inner = document.createElement('em'); inner.append(...child.childNodes); wrapper.append(inner); }
                    else wrapper.append(...child.childNodes);
                    child.replaceWith(wrapper);
                } else child.replaceWith(...child.childNodes);
                continue;
            }
            if (!ALLOWED_TAGS.has(child.tagName)) {
                clean(child);
                child.replaceWith(...child.childNodes);
                continue;
            }
            const attributes = Object.fromEntries([...child.attributes].map(attribute => [attribute.name.toLowerCase(), attribute.value]));
            for (const attribute of [...child.attributes]) child.removeAttribute(attribute.name);
            if (child.tagName === 'A' && safeUrl(attributes.href || '')) {
                child.setAttribute('href', attributes.href);
                if (attributes.title) child.setAttribute('title', attributes.title.slice(0, 191));
                if (attributes.target === '_blank') { child.setAttribute('target', '_blank'); child.setAttribute('rel', 'noopener noreferrer'); }
            }
            if (child.tagName === 'IMG' && safeUrl(attributes.src || '')) {
                child.setAttribute('src', attributes.src);
                child.setAttribute('alt', attributes.alt || '');
                for (const key of ['width', 'height']) if (/^[1-9]\d{0,3}$/.test(attributes[key] || '')) child.setAttribute(key, attributes[key]);
            }
            if (child.tagName === 'IMG' && !child.hasAttribute('src')) { child.remove(); continue; }
            if (child.tagName === 'CODE' && /^language-(text|php|javascript|typescript|html|css|bash|json|sql|python|go)$/.test(attributes.class || '')) child.className = attributes.class;
            if (child.tagName === 'FIGURE' && /^article-image article-image--(small|medium|large|full) article-image--(left|center|right)$/.test(attributes.class || '')) child.className = attributes.class;
            if (/^(P|H[1-6]|BLOCKQUOTE)$/.test(child.tagName) && /^article-align-(left|center|right|justify)$/.test(attributes.class || '')) child.className = attributes.class;
            clean(child);
        }
    };
    clean(template.content);
    return template.innerHTML;
}

export function initArticleEditor() {
    const field = document.querySelector('[data-article-editor-field]');
    if (!field || field.dataset.bound) return null;
    field.dataset.bound = '1';
    const form = field.closest('form');
    const editor = field.querySelector('[data-article-editor]');
    const source = field.querySelector('#content');
    const sourceArea = field.querySelector('[data-editor-source]');
    const shell = field.querySelector('[data-editor-shell]');
    const status = field.querySelector('[data-save-status]');
    const structure = field.querySelector('[data-editor-structure]');
    const count = field.querySelector('[data-editor-count]');
    const blockSelect = field.querySelector('[data-editor-block]');
    const abort = new AbortController();
    const listen = (target, name, callback, options = {}) => target?.addEventListener(name, callback, { ...options, signal: abort.signal });
    const get = selector => field.querySelector(selector);
    const key = `besofton-draft:${location.pathname}`;
    let dirty = false;
    let sourceMode = false;
    let timer;
    let statsTimer;
    let previewUrl;
    let savedRange;
    let selectedFigure;
    let selectedTableCell;
    let selectedCode;
    let selectedLink;
    let selectedYoutube;
    let chosenMedia;
    const mediaGallery = initMediaGallery(get('[data-editor-image-library-pane] [data-media-gallery]'), item => {
        chosenMedia = item;
        if (get('[data-editor-image-file]')) get('[data-editor-image-file]').value = '';
        get('[data-editor-image-alt]').value = item.alt || '';
        get('[data-editor-alt-warning]').hidden = !!get('[data-editor-image-alt]').value.trim();
        get('[data-editor-image-caption]').value = item.caption || '';
        showImagePreview(item.url);
    });
    let editFigure = false;
    let history = [editor.innerHTML];
    let historyIndex = 0;

    function selectionElement() {
        const node = window.getSelection()?.anchorNode;
        return node?.nodeType === Node.ELEMENT_NODE ? node : node?.parentElement;
    }

    function rememberSelection() {
        const selection = window.getSelection();
        if (selection?.rangeCount && editor.contains(selection.anchorNode)) savedRange = selection.getRangeAt(0).cloneRange();
    }

    function restoreSelection() {
        editor.focus();
        if (!savedRange || !editor.contains(savedRange.commonAncestorContainer)) return;
        const selection = window.getSelection();
        selection.removeAllRanges(); selection.addRange(savedRange);
    }

    function snapshot() {
        const clone = editor.cloneNode(true);
        clone.querySelectorAll('[data-editor-transient]').forEach(node => node.remove());
        clone.querySelectorAll('[data-editor-youtube]').forEach(node => {
            const paragraph = document.createElement('p');
            paragraph.textContent = `[youtube:${node.dataset.editorYoutube}]`;
            node.replaceWith(paragraph);
        });
        return cleanHtml(clone.innerHTML);
    }

    function sync() { source.value = sourceMode ? cleanHtml(sourceArea.value) : snapshot(); }

    function updateHistory() {
        if (sourceMode) return;
        const html = snapshot();
        if (html === history[historyIndex]) return;
        history = history.slice(0, historyIndex + 1);
        history.push(html);
        if (history.length > 200) history.shift();
        historyIndex = history.length - 1;
        get('[data-editor-command="undo"]').disabled = historyIndex === 0;
        get('[data-editor-command="redo"]').disabled = true;
    }

    function updateStats() {
        const text = (sourceMode ? sourceArea.value.replace(/<[^>]+>/g, ' ') : editor.textContent).trim();
        const words = text.match(/[\p{L}\p{N}]+(?:['’-][\p{L}\p{N}]+)*/gu) || [];
        count.textContent = `${new Intl.NumberFormat('id-ID').format(words.length)} kata · ±${Math.ceil(words.length / 200)} menit baca`;
        const headings = [...editor.querySelectorAll('h1,h2,h3,h4,h5,h6')];
        const warnings = [];
        const bodyH1 = headings.filter(heading => heading.tagName === 'H1').length;
        if (bodyH1) warnings.push('Judul artikel pada halaman publik sudah menggunakan H1. Untuk struktur SEO yang lebih baik, gunakan H2 untuk bagian utama artikel.');
        if (bodyH1 > 1) warnings.push(`Ada ${bodyH1} H1 tambahan di isi artikel. Periksa kembali hierarki heading.`);
        let previous = 1;
        for (const heading of headings) {
            const level = Number(heading.tagName[1]);
            if (level > previous + 1) warnings.push(`Hierarki melompat dari H${previous} ke H${level}: ${heading.textContent.trim()}`);
            previous = level;
        }
        structure.replaceChildren();
        const headingLabel = document.createElement('p'); headingLabel.textContent = 'Struktur heading: H1 Judul halaman'; structure.append(headingLabel);
        for (const heading of headings) {
            const line = document.createElement('p');
            line.style.paddingLeft = `${(Number(heading.tagName[1]) - 1) * 12}px`;
            line.textContent = `${heading.tagName} ${heading.textContent.trim().slice(0, 120)}`;
            structure.append(line);
        }
        for (const warning of warnings) {
            const line = document.createElement('p'); line.className = 'editor-warning'; line.textContent = warning; structure.append(line);
        }
    }

    function change() {
        dirty = true;
        sync();
        status.textContent = 'Perubahan belum disimpan';
        updateHistory();
        clearTimeout(timer);
        timer = setTimeout(() => { sync(); localStorage.setItem(key, source.value); status.textContent = 'Draf lokal tersimpan. Tekan Simpan untuk menyimpan ke CMS.'; }, 1200);
        clearTimeout(statsTimer);
        statsTimer = setTimeout(updateStats, 160);
    }

    function insertHtml(html) {
        restoreSelection();
        document.execCommand('insertHTML', false, cleanHtml(html));
        decorateImages();
        change();
    }

    function activeState(target = null) {
        const node = target || selectionElement();
        if (!node || !editor.contains(node)) return;
        const block = node.closest('p,h1,h2,h3,h4,h5,h6');
        blockSelect.value = block ? block.tagName.toLowerCase() : 'p';
        selectedFigure = node.closest('figure.article-image');
        selectedTableCell = node.closest('td,th');
        selectedCode = node.closest('pre')?.querySelector('code') || null;
        selectedLink = node.closest('a[href]');
        selectedYoutube = node.closest('[data-editor-youtube]');
        for (const [type, enabled] of [['image', !!selectedFigure], ['table', !!selectedTableCell], ['code', !!selectedCode], ['link', !!selectedLink], ['youtube', !!selectedYoutube]]) get(`[data-editor-${type}-context]`).hidden = !enabled;
        if (selectedFigure) {
            get('[data-editor-image-size]').value = SIZE_VALUES.find(size => selectedFigure.classList.contains(`article-image--${size}`)) || 'medium';
            get('[data-editor-image-align]').value = ALIGN_VALUES.find(align => selectedFigure.classList.contains(`article-image--${align}`)) || 'center';
            addResizeHandle(selectedFigure);
        } else editor.querySelectorAll('[data-editor-transient]').forEach(node => node.remove());
        if (selectedCode) get('[data-editor-code-language]').value = LANGUAGES.find(language => selectedCode.classList.contains(`language-${language}`)) || 'text';
        for (const command of ['bold', 'italic', 'underline', 'strike']) {
            const button = get(`[data-editor-command="${command}"]`);
            button.setAttribute('aria-pressed', String(document.queryCommandState(({ strike: 'strikeThrough' })[command] || command)));
        }
    }

    function addResizeHandle(figure) {
        if (figure.querySelector(':scope > [data-editor-transient]')) return;
        editor.querySelectorAll('[data-editor-transient]').forEach(node => node.remove());
        const handle = document.createElement('button');
        handle.type = 'button'; handle.className = 'article-image-handle'; handle.dataset.editorTransient = '1';
        handle.contentEditable = 'false'; handle.title = 'Seret untuk mengubah ukuran gambar'; handle.setAttribute('aria-label', 'Seret untuk mengubah ukuran gambar');
        figure.append(handle);
        handle.addEventListener('pointerdown', event => {
            event.preventDefault(); event.stopPropagation();
            const startX = event.clientX;
            const startWidth = figure.getBoundingClientRect().width;
            const available = editor.getBoundingClientRect().width;
            const presets = [['small', 320], ['medium', 560], ['large', 760], ['full', available]];
            const move = moveEvent => {
                const desired = Math.max(180, Math.min(available, startWidth + moveEvent.clientX - startX));
                const size = presets.reduce((best, item) => Math.abs(item[1] - desired) < Math.abs(best[1] - desired) ? item : best)[0];
                figure.className = imageClass(size, get('[data-editor-image-align]').value);
                get('[data-editor-image-size]').value = size;
            };
            const end = () => { document.removeEventListener('pointermove', move); document.removeEventListener('pointerup', end); change(); };
            document.addEventListener('pointermove', move, { signal: abort.signal }); document.addEventListener('pointerup', end, { once: true, signal: abort.signal });
        });
    }

    function openDialog(kind) {
        rememberSelection();
        const dialog = get(`[data-editor-dialog="${kind}"]`);
        if (kind === 'link') {
            get('[data-editor-link-url]').value = selectedLink?.getAttribute('href') || '';
            get('[data-editor-link-title]').value = selectedLink?.getAttribute('title') || '';
            get('[data-editor-link-new-tab]').checked = selectedLink?.getAttribute('target') === '_blank';
            get('[data-editor-link-remove]').hidden = !selectedLink;
            get('[data-editor-link-error]').hidden = true;
        }
        if (kind === 'image') {
            editFigure = !!selectedFigure;
            chosenMedia = editFigure ? { url: selectedFigure.querySelector('img')?.src, width: selectedFigure.querySelector('img')?.getAttribute('width'), height: selectedFigure.querySelector('img')?.getAttribute('height') } : null;
            get('[data-editor-image-file]') && (get('[data-editor-image-file]').value = '');
            get('[data-editor-image-alt]').value = editFigure ? selectedFigure.querySelector('img')?.alt || '' : '';
            get('[data-editor-alt-warning]').hidden = !!get('[data-editor-image-alt]').value.trim();
            get('[data-editor-image-caption]').value = editFigure ? selectedFigure.querySelector('figcaption')?.textContent || '' : '';
            get('[data-editor-image-dialog-size]').value = editFigure ? get('[data-editor-image-size]').value : 'medium';
            get('[data-editor-image-dialog-align]').value = editFigure ? get('[data-editor-image-align]').value : 'center';
            get('[data-editor-image-error]').hidden = true;
            showImagePreview(chosenMedia?.url);
            imageTab('upload');
        }
        if (kind === 'code') { get('[data-editor-code-input]').value = ''; get('[data-editor-code-insert-language]').value = 'php'; }
        dialog.showModal();
    }

    function showImagePreview(url) {
        const image = get('[data-editor-image-preview]');
        image.hidden = !url;
        if (url) image.src = url;
        else image.removeAttribute('src');
    }

    function imageTab(tab) {
        get('[data-editor-image-upload-pane]').hidden = tab !== 'upload';
        get('[data-editor-image-library-pane]').hidden = tab !== 'library';
        if (tab === 'library' && get('[data-editor-image-file]')) get('[data-editor-image-file]').value = '';
        if (tab === 'library') mediaGallery.load();
        for (const button of field.querySelectorAll('[data-editor-image-tab]')) button.setAttribute('aria-pressed', String(button.dataset.editorImageTab === tab));
    }

    function applyImage() {
        const file = get('[data-editor-image-file]')?.files?.[0];
        const alt = get('[data-editor-image-alt]').value.trim();
        const caption = get('[data-editor-image-caption]').value.trim();
        const size = get('[data-editor-image-dialog-size]').value;
        const align = get('[data-editor-image-dialog-align]').value;
        const error = get('[data-editor-image-error]');
        error.hidden = true;
        const finish = media => {
            if (!media?.url || !safeUrl(media.url)) { error.textContent = 'Pilih gambar yang valid.'; error.hidden = false; return; }
            const figure = editFigure && selectedFigure?.isConnected ? selectedFigure : document.createElement('figure');
            figure.className = imageClass(size, align); figure.contentEditable = 'false';
            const image = document.createElement('img'); image.src = media.url; image.alt = alt;
            if (media.width && media.height) { image.width = Number(media.width); image.height = Number(media.height); }
            figure.replaceChildren(image);
            if (caption) { const figcaption = document.createElement('figcaption'); figcaption.textContent = caption; figure.append(figcaption); }
            if (!editFigure) {
                insertHtml(`${figure.outerHTML}<p><br></p>`);
                selectedFigure = [...editor.querySelectorAll('figure.article-image')].reverse().find(item => item.querySelector('img')?.src === image.src) || null;
            }
            else change();
            get('[data-editor-dialog="image"]').close();
            if (editFigure) selectedFigure = figure;
        };
        if (file) {
            if (!alt) { error.textContent = 'Isi alt text sebelum mengunggah gambar.'; error.hidden = false; return; }
            const button = get('[data-editor-image-insert]'); button.disabled = true; button.textContent = 'Mengunggah...';
            uploadMedia(field.dataset.mediaUploadUrl, file, alt, caption)
                .then(finish)
                .catch(exception => { error.textContent = exception.message; error.hidden = false; })
                .finally(() => { button.disabled = false; button.textContent = 'Sisipkan gambar'; });
        } else finish(chosenMedia);
    }

    function tableAction(action) {
        const cell = selectedTableCell;
        const row = cell?.closest('tr');
        const table = row?.closest('table');
        if (!table) return;
        const index = [...row.children].indexOf(cell);
        const makeCell = tag => { const result = document.createElement(tag); result.textContent = ' '; return result; };
        if (action === 'row-above' || action === 'row-below') {
            const copy = row.cloneNode(false);
            for (const child of row.children) copy.append(makeCell(child.tagName.toLowerCase()));
            row.parentNode.insertBefore(copy, action === 'row-above' ? row : row.nextSibling);
        } else if (action === 'col-left' || action === 'col-right') {
            for (const current of table.querySelectorAll('tr')) {
                const reference = current.children[index];
                current.insertBefore(makeCell(reference?.tagName.toLowerCase() || 'td'), action === 'col-left' ? reference : reference?.nextSibling || null);
            }
        } else if (action === 'row-delete') {
            if (table.querySelectorAll('tr').length === 1) table.remove(); else row.remove();
        } else if (action === 'col-delete') {
            if (row.children.length === 1) table.remove(); else for (const current of table.querySelectorAll('tr')) current.children[index]?.remove();
        } else if (action === 'header-toggle') {
            for (const current of table.querySelector('tr')?.children || []) {
                const replacement = document.createElement(current.tagName === 'TH' ? 'td' : 'th'); replacement.innerHTML = current.innerHTML; current.replaceWith(replacement);
            }
        } else if (action === 'table-delete') table.remove();
        selectedTableCell = null; get('[data-editor-table-context]').hidden = true; change();
    }

    function youtubeId(url) { return url?.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/)?.[1]; }

    function decorateYoutube() {
        for (const paragraph of editor.querySelectorAll('p')) {
            const match = paragraph.textContent.trim().match(/^\[youtube:([A-Za-z0-9_-]{11})\]$/);
            if (!match) continue;
            const box = document.createElement('div'); box.className = 'editor-youtube'; box.dataset.editorYoutube = match[1]; box.contentEditable = 'false';
            const image = document.createElement('img'); image.src = `https://i.ytimg.com/vi/${match[1]}/hqdefault.jpg`; image.alt = 'Pratinjau YouTube';
            box.append(image); paragraph.replaceWith(box);
        }
    }

    function decorateImages() {
        for (const image of editor.querySelectorAll('img')) {
            if (image.closest('[data-editor-youtube]')) continue;
            let figure = image.closest('figure');
            if (!figure) {
                figure = document.createElement('figure');
                const paragraph = image.parentElement?.tagName === 'P' && image.parentElement.textContent.trim() === '' ? image.parentElement : null;
                if (paragraph) paragraph.replaceWith(figure);
                else image.replaceWith(figure);
                figure.append(image);
            }
            if (!figure.classList.contains('article-image')) figure.className = imageClass('medium', 'center');
            figure.contentEditable = 'false';
        }
    }

    async function restoreDraft() {
        const local = localStorage.getItem(key);
        if (!local || local === snapshot() || local === source.value) return;
        const Swal = (await import('sweetalert2')).default;
        const result = await Swal.fire({ title: 'Pulihkan draf lokal?', text: 'Ada perubahan yang belum disimpan di browser ini.', showCancelButton: true, confirmButtonText: 'Pulihkan', cancelButtonText: 'Abaikan' });
        if (!field.isConnected) return;
        if (result.isConfirmed) { editor.innerHTML = cleanHtml(local); decorateYoutube(); decorateImages(); change(); }
        else localStorage.removeItem(key);
    }

    listen(editor, 'input', change);
    listen(editor, 'click', event => { activeState(event.target); if (event.target.closest('a[href]')) event.preventDefault(); });
    listen(editor, 'keyup', () => { rememberSelection(); activeState(); });
    listen(document, 'selectionchange', () => { if (editor.contains(window.getSelection()?.anchorNode)) { rememberSelection(); activeState(); } });
    listen(get('.editor-toolbar'), 'mousedown', event => { if (event.target.closest('button')) { rememberSelection(); event.preventDefault(); } else rememberSelection(); });
    listen(get('.editor-toolbar'), 'click', event => {
        const button = event.target.closest('[data-editor-command]'); if (!button) return;
        const action = button.dataset.editorCommand;
        if (sourceMode && !['source', 'focus'].includes(action)) return;
        if (['link', 'image', 'code'].includes(action)) { openDialog(action); return; }
        if (action === 'source') {
            sourceMode = !sourceMode;
            if (sourceMode) { sourceArea.value = snapshot(); editor.hidden = true; sourceArea.hidden = false; field.querySelectorAll('.editor-context').forEach(context => context.hidden = true); sourceArea.focus(); }
            else { editor.innerHTML = cleanHtml(sourceArea.value); decorateYoutube(); decorateImages(); sourceArea.hidden = true; editor.hidden = false; editor.focus(); }
            button.setAttribute('aria-pressed', String(sourceMode)); change(); return;
        }
        if (action === 'focus') { shell.classList.toggle('editor-focus'); button.setAttribute('aria-pressed', String(shell.classList.contains('editor-focus'))); return; }
        if (action === 'undo' || action === 'redo') {
            const next = historyIndex + (action === 'undo' ? -1 : 1);
            if (next < 0 || next >= history.length) return;
            historyIndex = next; editor.innerHTML = history[next]; decorateYoutube(); decorateImages(); editor.focus(); sync(); dirty = true; updateStats();
            get('[data-editor-command="undo"]').disabled = historyIndex === 0;
            get('[data-editor-command="redo"]').disabled = historyIndex === history.length - 1;
            return;
        }
        if (action === 'table') { insertHtml('<table><tbody><tr><th>Kolom 1</th><th>Kolom 2</th><th>Kolom 3</th></tr><tr><td>Isi</td><td>Isi</td><td>Isi</td></tr><tr><td>Isi</td><td>Isi</td><td>Isi</td></tr></tbody></table><p><br></p>'); return; }
        if (action === 'youtube') { const id = youtubeId(prompt('Tempel URL video YouTube')); if (id) { insertHtml(`<p>[youtube:${id}]</p><p><br></p>`); decorateYoutube(); } return; }
        restoreSelection();
        if (action.startsWith('align-')) {
            const block = selectionElement()?.closest('p,h1,h2,h3,h4,h5,h6,blockquote');
            if (block) block.className = `article-align-${action.slice(6)}`;
        } else if (action === 'inline-code') {
            const selected = window.getSelection()?.toString() || 'kode';
            document.execCommand('insertHTML', false, `<code>${escapeHtml(selected)}</code>`);
        } else if (action === 'hr') document.execCommand('insertHorizontalRule');
        else if (action === 'clear') document.execCommand('removeFormat');
        else document.execCommand(({ unordered: 'insertUnorderedList', ordered: 'insertOrderedList', quote: 'formatBlock', strike: 'strikeThrough' })[action] || action, false, action === 'quote' ? 'blockquote' : undefined);
        change(); activeState();
    });
    listen(blockSelect, 'change', () => { if (sourceMode) return; restoreSelection(); document.execCommand('formatBlock', false, blockSelect.value); change(); editor.focus(); });
    listen(editor, 'keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openDialog('link'); }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z') { event.preventDefault(); get(`[data-editor-command="${event.shiftKey ? 'redo' : 'undo'}"]`).click(); }
        if (event.key === 'Tab' && selectionElement()?.closest('pre')) { event.preventDefault(); document.execCommand('insertText', false, '    '); }
    });
    listen(document, 'keydown', event => { if (event.key === 'Escape' && shell.classList.contains('editor-focus')) { shell.classList.remove('editor-focus'); get('[data-editor-command="focus"]').setAttribute('aria-pressed', 'false'); } });
    listen(sourceArea, 'input', change);
    listen(editor, 'paste', event => {
        const file = [...(event.clipboardData?.files || [])].find(item => item.type.startsWith('image/'));
        if (file) { event.preventDefault(); openDialog('image'); const input = get('[data-editor-image-file]'); if (input) { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; input.dispatchEvent(new Event('change')); } return; }
        event.preventDefault();
        const pre = selectionElement()?.closest('pre');
        if (pre) { document.execCommand('insertText', false, event.clipboardData.getData('text/plain')); return; }
        const html = event.clipboardData.getData('text/html');
        if (html) { insertHtml(html); if (/src\s*=\s*["']?data:image\//i.test(html)) status.textContent = 'Gambar tertanam tidak disimpan. Unggah melalui Media Library.'; }
        else {
            const text = event.clipboardData.getData('text/plain');
            insertHtml(text.split(/\n\s*\n/).map(part => `<p>${escapeHtml(part).replace(/\n/g, '<br>')}</p>`).join(''));
        }
    });
    listen(editor, 'drop', event => {
        const file = [...(event.dataTransfer?.files || [])].find(item => item.type.startsWith('image/'));
        if (!file) return;
        event.preventDefault(); openDialog('image');
        const input = get('[data-editor-image-file]'); if (input) { const transfer = new DataTransfer(); transfer.items.add(file); input.files = transfer.files; input.dispatchEvent(new Event('change')); }
    });
    listen(field, 'click', event => {
        if (event.target.closest('[data-editor-close]')) event.target.closest('dialog').close();
        const tab = event.target.closest('[data-editor-image-tab]'); if (tab) imageTab(tab.dataset.editorImageTab);
        if (event.target.closest('[data-editor-image-insert]')) applyImage();
        if (event.target.closest('[data-editor-image-edit]')) openDialog('image');
        if (event.target.closest('[data-editor-image-delete]') && selectedFigure) { selectedFigure.remove(); get('[data-editor-image-context]').hidden = true; change(); }
        if (event.target.closest('[data-editor-link-insert]')) {
            const url = get('[data-editor-link-url]').value.trim(); const error = get('[data-editor-link-error]');
            if (!safeUrl(url)) { error.textContent = 'URL harus http(s), path internal, atau anchor yang aman.'; error.hidden = false; return; }
            restoreSelection();
            if (selectedLink?.isConnected) selectedLink.setAttribute('href', url);
            else document.execCommand('createLink', false, url);
            const link = selectedLink?.isConnected ? selectedLink : selectionElement()?.closest('a');
            if (link) { const title = get('[data-editor-link-title]').value.trim(); if (title) link.title = title; else link.removeAttribute('title'); if (get('[data-editor-link-new-tab]').checked) { link.target = '_blank'; link.rel = 'noopener noreferrer'; } else { link.removeAttribute('target'); link.removeAttribute('rel'); } }
            get('[data-editor-dialog="link"]').close(); change();
        }
        if (event.target.closest('[data-editor-link-remove], [data-editor-link-unlink]')) { restoreSelection(); document.execCommand('unlink'); if (selectedLink?.isConnected) selectedLink.replaceWith(...selectedLink.childNodes); get('[data-editor-dialog="link"]').close(); change(); }
        if (event.target.closest('[data-editor-link-edit]')) openDialog('link');
        if (event.target.closest('[data-editor-link-open]') && selectedLink && safeUrl(selectedLink.href)) window.open(selectedLink.href, '_blank', 'noopener,noreferrer');
        if (event.target.closest('[data-editor-code-insert]')) {
            const language = get('[data-editor-code-insert-language]').value;
            const code = get('[data-editor-code-input]').value;
            if (code) { insertHtml(`<pre><code class="language-${language}">${escapeHtml(code)}</code></pre><p><br></p>`); get('[data-editor-dialog="code"]').close(); }
        }
        if (event.target.closest('[data-editor-code-remove]') && selectedCode) { selectedCode.closest('pre').remove(); get('[data-editor-code-context]').hidden = true; change(); }
        if (event.target.closest('[data-editor-youtube-edit]') && selectedYoutube) { const id = youtubeId(prompt('Tempel URL video YouTube')); if (id) { selectedYoutube.dataset.editorYoutube = id; selectedYoutube.querySelector('img').src = `https://i.ytimg.com/vi/${id}/hqdefault.jpg`; change(); } }
        if (event.target.closest('[data-editor-youtube-delete]') && selectedYoutube) { selectedYoutube.remove(); get('[data-editor-youtube-context]').hidden = true; change(); }
        const tableButton = event.target.closest('[data-editor-table-action]'); if (tableButton) tableAction(tableButton.dataset.editorTableAction);
    });
    listen(get('[data-editor-image-size]'), 'change', event => { if (selectedFigure) { selectedFigure.className = imageClass(event.target.value, get('[data-editor-image-align]').value); change(); } });
    listen(get('[data-editor-image-align]'), 'change', event => { if (selectedFigure) { selectedFigure.className = imageClass(get('[data-editor-image-size]').value, event.target.value); change(); } });
    listen(get('[data-editor-code-language]'), 'change', event => { if (selectedCode) { selectedCode.className = `language-${event.target.value}`; change(); } });
    listen(get('[data-editor-image-file]'), 'change', event => { if (previewUrl) URL.revokeObjectURL(previewUrl); chosenMedia = null; previewUrl = event.target.files?.[0] ? URL.createObjectURL(event.target.files[0]) : null; showImagePreview(previewUrl); });
    listen(get('[data-editor-image-alt]'), 'input', event => { get('[data-editor-alt-warning]').hidden = !!event.target.value.trim(); });
    listen(form, 'submit', () => { sync(); dirty = false; localStorage.removeItem(key); }, { capture: true });
    get('[data-editor-command="undo"]').disabled = true;
    get('[data-editor-command="redo"]').disabled = true;
    decorateYoutube(); decorateImages(); updateStats(); restoreDraft();

    return {
        get dirty() { return dirty; },
        clear() { dirty = false; },
        destroy() { abort.abort(); mediaGallery.destroy(); clearTimeout(timer); clearTimeout(statsTimer); if (previewUrl) URL.revokeObjectURL(previewUrl); editor.querySelectorAll('[data-editor-transient]').forEach(node => node.remove()); delete field.dataset.bound; },
    };
}
