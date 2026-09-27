import './bootstrap';
import { initNumericMasks } from './numeric-mask';
import Prism from 'prismjs';
import 'prismjs/components/prism-markup';
import 'prismjs/components/prism-css';
import 'prismjs/components/prism-javascript';
import 'prismjs/components/prism-typescript';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-sql';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-go';

let Swal;
let TomSelect;
let Calendar;
let dayGridPlugin;
let initGeneration = 0;
window.permissionMatrix = (initial, all) => ({
    selected: [...new Set(initial)],
    original: [...new Set(initial)].sort().join('|'),
    all,
    get dirty() { return [...this.selected].sort().join('|') !== this.original; },
    mark() { this.$root.dataset.dirty = this.dirty ? '1' : '0'; },
    toggle(name, checked) {
        if (checked) {
            if (!this.selected.includes(name)) this.selected.push(name);
        } else {
            this.selected = this.selected.filter(item => item !== name);
            if (name === 'cms.access') this.selected = [];
            else if (name.endsWith('.view')) {
                const module = name.split('.')[0];
                this.selected = this.selected.filter(item => !item.startsWith(`${module}.`));
            }
        }
        if (this.selected.length && !this.selected.includes('cms.access')) this.selected.push('cms.access');
        if (this.selected.length && !this.selected.includes('dashboard.view')) this.selected.push('dashboard.view');
        for (const item of [...this.selected]) {
            const view = `${item.split('.')[0]}.view`;
            if (this.all.includes(view) && !this.selected.includes(view)) this.selected.push(view);
        }
        this.mark();
    },
    toggleMany(names) {
        const enable = !names.every(name => this.selected.includes(name));
        for (const name of names) this.toggle(name, enable);
        this.mark();
    },
});
document.addEventListener('livewire:navigate', async event => {
    const form = document.querySelector('[data-permission-form][data-dirty="1"]');
    if (!form || form.dataset.saving) return;
    event.preventDefault();
    Swal ||= (await import('sweetalert2')).default;
    const result = await Swal.fire({ title: 'Perubahan belum disimpan', text: 'Keluar tanpa menyimpan permissions?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Keluar', cancelButtonText: 'Tetap di sini' });
    if (result.isConfirmed) { form.dataset.dirty = '0'; window.Livewire?.navigate(event.detail.url); }
});
document.addEventListener('submit', async event => {
    const form = event.target.closest('form[data-confirm]');
    if (!form || form.dataset.confirmed) return;
    event.preventDefault();
    Swal ||= (await import('sweetalert2')).default;
    const result = await Swal.fire({ title: form.dataset.confirm, icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' });
    if (result.isConfirmed) { form.dataset.confirmed = '1'; form.requestSubmit(event.submitter || undefined); }
}, true);
const selects = new Set();
let calendar = null;
let currentEditor = null;
async function initPage() {
    const generation = ++initGeneration;
    if (document.body?.dataset.cms === '1') {
        Swal ||= (await import('sweetalert2')).default;
        if (document.querySelector('[data-search-select]') && !TomSelect) {
            TomSelect = (await import('tom-select')).default;
            await import('tom-select/dist/css/tom-select.css');
        }
    }
    if (document.querySelector('[data-toast]') && !Swal) Swal = (await import('sweetalert2')).default;
    if (generation !== initGeneration) return;
    initEditor();
    initNumericMasks();
    const selectAll = document.querySelector('[data-select-all]');
    if (selectAll && !selectAll.dataset.bound) {
        selectAll.dataset.bound = '1';
        selectAll.addEventListener('change', event => {
            document.querySelectorAll('input[name="ids[]"]:not(:disabled)').forEach(input => { input.checked = event.target.checked; });
        });
    }
    document.querySelectorAll('[data-search-select]').forEach(element => {
        if (element.tomselect) return;
        selects.add(new TomSelect(element, { plugins: element.multiple ? ['remove_button'] : ['clear_button'], create: false, allowEmptyOption: true }));
    });
    const source = document.querySelector('[data-slug-source]');
    const target = document.querySelector('[data-slug-target]');
    if (source && target && !source.dataset.bound) {
        let manual = Boolean(target.value);
        target.addEventListener('input', () => { manual = Boolean(target.value); });
        source.addEventListener('input', () => {
            if (!manual) target.value = source.value.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        });
        source.dataset.bound = '1';
    }
    document.querySelectorAll('form').forEach(form => {
        if (form.dataset.submitBound || form.method.toLowerCase() === 'get' || form.hasAttribute('wire:submit')) return;
        form.dataset.submitBound = '1';
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1';
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('[data-submitter-clone]').forEach(input => input.remove());
            const submitter = event.submitter;
            if (submitter?.name) {
                const value = document.createElement('input');
                value.type = 'hidden'; value.name = submitter.name; value.value = submitter.value; value.dataset.submitterClone = '1';
                form.append(value);
            }
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
                button.disabled = true;
                button.classList.add('opacity-60', 'cursor-wait');
            });
        });
    });
    document.querySelectorAll('[data-toast]').forEach(element => {
        Swal.fire({ toast: true, position: 'top-end', icon: element.dataset.toastIcon || 'success', title: element.textContent.trim(), showConfirmButton: false, timer: 3000 });
        element.remove();
    });
    document.querySelectorAll('.article-content pre code').forEach(block => {
        if (block.dataset.ready) return;
        block.dataset.ready = '1';
        const wrapper = block.closest('pre');
        if (!wrapper) return;
        wrapper.classList.add('code-block');
        const toolbar = document.createElement('div');
        toolbar.className = 'code-toolbar';
        const label = document.createElement('span');
        label.textContent = [...block.classList].find(name => name.startsWith('language-'))?.replace('language-', '').toUpperCase() || 'CODE';
        const button = document.createElement('button');
        button.type = 'button'; button.textContent = 'Salin';
        button.addEventListener('click', async () => { await navigator.clipboard.writeText(block.textContent); button.textContent = 'Tersalin'; setTimeout(() => button.textContent = 'Salin', 1800); });
        toolbar.append(label, button); wrapper.prepend(toolbar);
    });
    document.querySelectorAll('[data-copy]').forEach(button => {
        if (button.dataset.bound) return;
        button.dataset.bound = '1';
        button.addEventListener('click', async () => { await navigator.clipboard.writeText(button.dataset.copy); button.textContent = 'URL tersalin'; });
    });
    const calendarElement = document.querySelector('#editorial-calendar');
    const eventsElement = document.querySelector('#editorial-events');
    if (calendarElement && eventsElement && !calendar) {
        if (!Calendar) {
            [{ Calendar }, { default: dayGridPlugin }] = await Promise.all([import('@fullcalendar/core'), import('@fullcalendar/daygrid')]);
        }
        if (generation !== initGeneration) return;
        calendar = new Calendar(calendarElement, { plugins: [dayGridPlugin], initialView: 'dayGridMonth', locale: 'id', events: JSON.parse(eventsElement.textContent), height: 'auto' });
        calendar.render();
    }
    document.querySelectorAll('[data-youtube]').forEach(placeholder => {
        if (placeholder.dataset.bound) return;
        placeholder.dataset.bound = '1';
        placeholder.querySelector('button')?.addEventListener('click', () => {
            const iframe = document.createElement('iframe');
            iframe.src = `https://www.youtube-nocookie.com/embed/${placeholder.dataset.youtube}?autoplay=1`;
            iframe.title = 'Video YouTube'; iframe.allowFullscreen = true; iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
            placeholder.replaceChildren(iframe);
        });
    });
    const toc = document.querySelector('#article-toc');
    if (toc && !toc.dataset.built) {
        const headings = [...document.querySelectorAll('.article-content h2, .article-content h3')];
        if (headings.length) {
            toc.classList.remove('hidden');
            const list = toc.querySelector('ol');
            headings.forEach((heading, index) => {
                heading.id ||= `bagian-${index + 1}`;
                const link = document.createElement('a');
                link.href = `#${heading.id}`; link.textContent = heading.textContent;
                link.className = 'text-indigo-700 hover:underline';
                const item = document.createElement('li');
                if (heading.tagName === 'H3') item.className = 'ml-4';
                item.append(link); list.append(item);
            });
        }
        toc.dataset.built = '1';
    }
    Prism.highlightAllUnder(document.querySelector('main') || document);
}
document.addEventListener('livewire:navigate', () => document.querySelector('#nav-progress')?.classList.remove('hidden'));
document.addEventListener('livewire:navigating', () => { initGeneration++; selects.forEach(select => select.destroy()); selects.clear(); calendar?.destroy(); calendar = null; currentEditor = null; });
document.addEventListener('livewire:navigated', () => { document.querySelector('#nav-progress')?.classList.add('hidden'); initPage(); });
document.addEventListener('DOMContentLoaded', initPage);
function initEditor() {
    const editor = document.querySelector('[data-article-editor]');
    const form = document.querySelector('[data-post-form]');
    const source = document.querySelector('#content');
    if (!editor || !form || !source || editor.dataset.bound) return;
    editor.dataset.bound = '1';
    const status = document.querySelector('[data-save-status]');
    const key = `besofton-draft:${location.pathname}`;
    let dirty = false;
    let timer;
    const escaped = value => value.replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const sync = () => { source.value = editor.innerHTML; };
    const saveLocal = () => { sync(); localStorage.setItem(key, source.value); if (status) status.textContent = 'Draf lokal tersimpan. Tekan Simpan untuk menyimpan ke CMS.'; };
    editor.addEventListener('input', () => { dirty = true; sync(); if (status) status.textContent = 'Perubahan belum tersimpan'; clearTimeout(timer); timer = setTimeout(saveLocal, 1200); });
    document.querySelectorAll('[data-editor-command]').forEach(button => button.addEventListener('click', () => {
        editor.focus();
        const action = button.dataset.editorCommand;
        if (action === 'h2' || action === 'h3') document.execCommand('formatBlock', false, action);
        else if (action === 'bold' || action === 'italic') document.execCommand(action);
        else if (action === 'unordered') document.execCommand('insertUnorderedList');
        else if (action === 'ordered') document.execCommand('insertOrderedList');
        else if (action === 'quote') document.execCommand('formatBlock', false, 'blockquote');
        else if (action === 'hr') document.execCommand('insertHorizontalRule');
        else if (action === 'link' || action === 'image') {
            const url = prompt(action === 'link' ? 'URL tautan (https://)' : 'URL gambar (https://)');
            if (url && /^https?:\/\//i.test(url)) {
                if (action === 'link') document.execCommand('createLink', false, url);
                else document.execCommand('insertImage', false, url);
            }
        } else if (action === 'youtube') {
            const url = prompt('Tempel URL video YouTube');
            const id = url?.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([a-zA-Z0-9_-]{11})/)?.[1];
            if (id) document.execCommand('insertHTML', false, `<p>[youtube:${id}]</p>`);
            else if (url) Swal.fire({icon:'error', title:'URL YouTube tidak valid'});
        } else if (action === 'code') {
            const language = (prompt('Bahasa kode (php, javascript, sql, dll.)', 'php') || 'text').toLowerCase().replace(/[^a-z0-9+#-]/g, '');
            const code = prompt('Tempel kode');
            if (code) document.execCommand('insertHTML', false, `<pre><code class="language-${language}">${escaped(code)}</code></pre><p><br></p>`);
        }
        editor.dispatchEvent(new Event('input'));
    }));
    const local = localStorage.getItem(key);
    if (local && local !== editor.innerHTML && local !== source.value) {
        Swal.fire({title:'Pulihkan draf lokal?', text:'Ada perubahan yang belum disimpan di browser ini.', showCancelButton:true, confirmButtonText:'Pulihkan', cancelButtonText:'Abaikan'}).then(result => {
            if (result.isConfirmed) { editor.innerHTML = local; editor.dispatchEvent(new Event('input')); }
            else localStorage.removeItem(key);
        });
    }
    form.addEventListener('submit', () => { sync(); dirty = false; localStorage.removeItem(key); });
    currentEditor = { get dirty() { return dirty; }, clear() { dirty = false; } };
}
window.addEventListener('beforeunload', event => { if (currentEditor?.dirty) { event.preventDefault(); event.returnValue = ''; } });
document.addEventListener('livewire:navigate', event => {
    if (!currentEditor?.dirty) return;
    event.preventDefault();
    Swal.fire({title:'Perubahan belum disimpan', text:'Keluar dari editor tanpa menyimpan?', showCancelButton:true, confirmButtonText:'Keluar', cancelButtonText:'Tetap menulis'}).then(result => {
        if (result.isConfirmed) { currentEditor.clear(); window.Livewire?.navigate(event.detail.url); }
    });
});window.addEventListener('scroll', () => {
    const bar = document.querySelector('#reading-progress');
    const article = document.querySelector('article');
    if (!bar || !article) return;
    const max = Math.max(1, article.offsetHeight - window.innerHeight);
    const read = Math.max(0, Math.min(100, ((window.scrollY - article.offsetTop) / max) * 100));
    bar.style.width = `${read}%`;
}, { passive: true });
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach(form => {
        delete form.dataset.submitting;
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-submitter-clone]').forEach(input => input.remove());
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = false; button.classList.remove('opacity-60', 'cursor-wait'); });
    });
});
