// Progressive enhancement: keep the native POST/PATCH form and its CSRF field.
// Failed requests stay in the editor, including hosting errors outside Laravel.
export function initArticleSave(form) {
    const errorBox = form.querySelector('[data-post-save-error]');
    let pending = false;
    const showError = messages => {
        errorBox.replaceChildren();
        for (const message of messages) {
            const line = document.createElement('p');
            line.textContent = message;
            errorBox.append(line);
        }
        errorBox.hidden = false;
    };
    const submit = async event => {
        if (event.defaultPrevented) return;
        event.preventDefault();
        if (pending) return;
        pending = true;
        form.dispatchEvent(new Event('article:before-save'));
        const payload = new FormData(form);
        errorBox.hidden = true;
        const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type])')];
        const disabled = buttons.map(button => button.disabled);
        buttons.forEach(button => { button.disabled = true; });
        form.dataset.submitting = '1';
        form.setAttribute('aria-busy', 'true');
        form.inert = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 45000);
        let saved = false;
        try {
            const response = await fetch(form.action, {
                method: 'POST', body: payload, credentials: 'same-origin', redirect: 'error', signal: controller.signal,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!form.isConnected) return;
            if (response.status === 422) {
                const result = await response.json();
                const messages = Object.values(result.errors || {}).flat().filter(value => typeof value === 'string');
                showError(['Artikel belum disimpan. Periksa isian berikut:', ...messages]);
                return;
            }
            if (!response.ok) {
                const reasons = {
                    401: 'Sesi login berakhir. Buka login di tab lain, lalu kembali ke editor ini.',
                    419: 'Sesi atau token keamanan kedaluwarsa. Salin konten sebelum memuat ulang dan login kembali.',
                    403: 'Penyimpanan ditolak oleh izin akses atau filter keamanan hosting. Hubungi pengelola bila berulang.',
                    405: 'Metode penyimpanan ditolak. Pengelola perlu memeriksa redirect dan filter hosting.',
                    413: 'Ukuran request melebihi batas server. Hubungi pengelola untuk memeriksa batas hosting.',
                    429: 'Terlalu banyak request. Tunggu sebelum mencoba kembali.',
                };
                showError([reasons[response.status] || 'Server belum dapat mengonfirmasi penyimpanan. Periksa daftar artikel sebelum mencoba kembali.', 'Konten tetap tersedia di editor ini.']);
                return;
            }
            const result = await response.json();
            const destination = new URL(result.redirect, location.href);
            const base = new URL(form.dataset.editBase, location.href);
            const suffix = destination.pathname.slice(base.pathname.length);
            if (destination.origin !== location.origin || !destination.pathname.startsWith(base.pathname) || !/^\/[0-9]+\/edit$/.test(suffix) || destination.search || destination.hash) {
                throw new Error('Unexpected save response');
            }
            saved = true;
            form.dispatchEvent(new Event('article:saved'));
            location.assign(destination.href);
        } catch {
            if (form.isConnected) showError(['Hasil penyimpanan belum dapat dikonfirmasi: koneksi terputus atau respons tidak sesuai. Konten tetap tersedia di editor ini.', 'Periksa daftar artikel di tab lain sebelum mencoba kembali untuk menghindari artikel ganda.']);
        } finally {
            clearTimeout(timeout);
            pending = false;
            form.inert = false;
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            buttons.forEach((button, index) => { button.disabled = disabled[index]; });
            if (!saved && form.isConnected && !errorBox.hidden) errorBox.focus();
        }
    };
    form.addEventListener('submit', submit);
    return { destroy() { form.removeEventListener('submit', submit); } };
}
