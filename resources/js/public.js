function initPublicPage() {
    document.querySelectorAll('[data-toast]').forEach(element => {
        const toast = document.createElement('div');
        toast.className = 'public-toast'; toast.setAttribute('role', 'status');
        toast.textContent = element.textContent.trim(); document.body.append(toast);
        element.remove(); setTimeout(() => toast.remove(), 4000);
    });
    document.querySelectorAll('.article-content pre code').forEach(block => {
        if (block.dataset.ready) return;
        block.dataset.ready = '1';
        const wrapper = block.closest('pre'); if (!wrapper) return;
        wrapper.classList.add('code-block');
        const toolbar = document.createElement('div'); toolbar.className = 'code-toolbar';
        const label = document.createElement('span');
        label.textContent = [...block.classList].find(name => name.startsWith('language-'))?.replace('language-', '').toUpperCase() || 'CODE';
        const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Salin kode';
        button.addEventListener('click', async () => { await navigator.clipboard.writeText(block.textContent); button.textContent = 'Tersalin'; setTimeout(() => button.textContent = 'Salin kode', 1800); });
        toolbar.append(label, button); wrapper.prepend(toolbar);
    });
    document.querySelectorAll('[data-copy]').forEach(button => {
        if (button.dataset.bound) return;
        button.dataset.bound = '1'; button.addEventListener('click', async () => { await navigator.clipboard.writeText(button.dataset.copy); button.textContent = 'Tautan tersalin'; });
    });
    document.querySelectorAll('[data-youtube]').forEach(placeholder => {
        if (placeholder.dataset.bound) return;
        placeholder.dataset.bound = '1'; placeholder.querySelector('button')?.addEventListener('click', () => {
            const iframe = document.createElement('iframe'); iframe.src = `https://www.youtube-nocookie.com/embed/${placeholder.dataset.youtube}?autoplay=1`;
            iframe.title = 'Video YouTube'; iframe.allowFullscreen = true; iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture';
            placeholder.replaceChildren(iframe);
        });
    });
    const toc = document.querySelector('#article-toc');
    if (toc && !toc.dataset.built) {
        const headings = [...document.querySelectorAll('.article-content h2, .article-content h3')];
        if (headings.length) {
            toc.hidden = false; const list = toc.querySelector('ol');
            const desktopList = document.createElement('ol');
            headings.forEach((heading, index) => {
                heading.id ||= `bagian-${index + 1}`;
                const item = document.createElement('li'); if (heading.tagName === 'H3') item.className = 'toc-subitem';
                const link = document.createElement('a'); link.href = `#${heading.id}`; link.textContent = heading.textContent;
                item.append(link); list.append(item);
                const desktopItem = item.cloneNode(true);
                desktopList.append(desktopItem);
            });
            document.querySelector('#article-toc-desktop')?.append(desktopList);
        }
        toc.dataset.built = '1';
    }
    if (document.querySelector('.article-content pre code')) {
        import('prismjs').then(async ({ default: Prism }) => {
            await Promise.all([
                import('prismjs/components/prism-markup'), import('prismjs/components/prism-css'),
                import('prismjs/components/prism-javascript'), import('prismjs/components/prism-typescript'),
                import('prismjs/components/prism-php'), import('prismjs/components/prism-bash'),
                import('prismjs/components/prism-json'), import('prismjs/components/prism-sql'),
                import('prismjs/components/prism-python'), import('prismjs/components/prism-go'),
            ]);
            Prism.highlightAllUnder(document.querySelector('main') || document);
        });
    }
    document.querySelectorAll('form').forEach(form => {
        if (form.dataset.submitBound || form.method.toLowerCase() === 'get' || form.hasAttribute('wire:submit')) return;
        form.dataset.submitBound = '1'; form.addEventListener('submit', event => {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1'; form.setAttribute('aria-busy', 'true');
            const submitter = event.submitter;
            if (submitter?.name) { const input = document.createElement('input'); input.type = 'hidden'; input.name = submitter.name; input.value = submitter.value; input.dataset.submitterClone = '1'; form.append(input); }
            form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => { button.disabled = true; });
        });
    });
}
document.addEventListener('DOMContentLoaded', initPublicPage);
document.addEventListener('livewire:navigate', () => { const bar = document.querySelector('#nav-progress'); if (bar) bar.hidden = false; });
document.addEventListener('livewire:navigated', () => { const bar = document.querySelector('#nav-progress'); if (bar) bar.hidden = true; initPublicPage(); });
window.addEventListener('pageshow', () => document.querySelectorAll('form[data-submitting]').forEach(form => { delete form.dataset.submitting; form.removeAttribute('aria-busy'); form.querySelectorAll('button').forEach(button => { button.disabled = false; }); }));
window.addEventListener('scroll', () => { const bar = document.querySelector('#reading-progress'); const article = document.querySelector('[data-reading-article]'); if (!bar || !article) return; const max = Math.max(1, article.offsetHeight - innerHeight); bar.style.width = `${Math.max(0, Math.min(100, ((scrollY - article.offsetTop) / max) * 100))}%`; }, { passive: true });
