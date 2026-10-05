const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

export const safeImageUrl = value => {
    try {
        const url = new URL(value);
        return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password;
    } catch { return false; }
};

export async function uploadMedia(url, file, alt, caption = '') {
    const data = new FormData();
    data.append('file', file);
    data.append('alt_text', alt);
    data.append('caption', caption);
    const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: data });
    const result = await response.json();
    if (!response.ok) throw new Error(Object.values(result.errors || {})[0]?.[0] || result.message || 'Upload gambar gagal.');
    return result;
}

export function initMediaGallery(root, onSelect) {
    const search = root?.querySelector('[data-media-search]');
    if (!search) return { load() {}, destroy() {} };
    const results = root.querySelector('[data-media-results]');
    const more = root.querySelector('[data-media-more]');
    const empty = root.querySelector('[data-media-empty]');
    const error = root.querySelector('[data-media-error]');
    const abort = new AbortController();
    let request;
    let timer;
    let page = 0;
    let lastPage = 1;

    async function load(reset = false) {
        if (reset) { page = 0; results.replaceChildren(); }
        if (page >= lastPage && !reset) return;
        request?.abort();
        request = new AbortController();
        const nextPage = page + 1;
        const url = new URL(root.dataset.mediaIndexUrl, location.href);
        url.searchParams.set('page', String(nextPage));
        if (search.value.trim()) url.searchParams.set('q', search.value.trim());
        more.disabled = true;
        more.textContent = 'Memuat...';
        error.hidden = true;
        empty.hidden = true;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: request.signal });
            if (!response.ok) throw new Error('Media Library tidak dapat dimuat. Coba lagi.');
            const data = await response.json();
            for (const item of data.items) {
                if (!safeImageUrl(item.url)) continue;
                const button = document.createElement('button');
                button.type = 'button'; button.className = 'article-media-item';
                button.setAttribute('aria-label', `Pilih ${item.alt || 'gambar'}`);
                const image = document.createElement('img'); image.src = item.url; image.alt = ''; image.loading = 'lazy';
                const label = document.createElement('span'); label.textContent = item.alt || item.url.split('/').pop();
                button.append(image, label);
                button.addEventListener('click', () => {
                    root.querySelectorAll('.article-media-item[aria-pressed="true"]').forEach(other => other.setAttribute('aria-pressed', 'false'));
                    button.setAttribute('aria-pressed', 'true'); onSelect(item);
                }, { signal: abort.signal });
                results.append(button);
            }
            page = data.current_page;
            lastPage = data.last_page;
            more.hidden = page >= lastPage;
            empty.hidden = results.children.length > 0;
        } catch (exception) {
            if (exception.name !== 'AbortError') { error.textContent = exception.message; error.hidden = false; more.hidden = false; }
        } finally { more.disabled = false; more.textContent = 'Muat lagi'; }
    }
    search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(true), 280); }, { signal: abort.signal });
    more.addEventListener('click', () => load(), { signal: abort.signal });
    return { load: () => load(true), destroy() { abort.abort(); request?.abort(); clearTimeout(timer); } };
}
