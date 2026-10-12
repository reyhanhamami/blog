export const sidebarState = (preference, width) =>
    ['expanded', 'collapsed'].includes(preference) ? preference : width < 1440 ? 'collapsed' : 'expanded';

export const analyzerFits = width => width >= 1024;

export function initCmsShell() {
    const shell = document.querySelector('[data-cms-shell]');
    if (!shell) return { destroy() {} };
    const abort = new AbortController();
    const listen = (node, event, callback) => node.addEventListener(event, callback, { signal: abort.signal });
    const sidebar = shell.querySelector('[data-cms-sidebar]');
    const main = shell.querySelector('.admin-main');
    const toggle = shell.querySelector('[data-sidebar-toggle]');
    const backdrop = shell.querySelector('[data-sidebar-backdrop]');
    const tooltip = shell.querySelector('[data-sidebar-tooltip]');
    const mobile = matchMedia('(max-width: 1023px)');
    const compact = matchMedia('(max-width: 1439px)');
    let preference;
    let opened = false;
    let previousOverflow;
    try { preference = localStorage.getItem('cms-sidebar-state'); } catch { /* Storage can be disabled. */ }
    function render() {
        const state = sidebarState(preference, window.innerWidth);
        document.documentElement.dataset.cmsSidebar = state;
        shell.dataset.sidebarOpen = String(opened);
        toggle.setAttribute('aria-expanded', String(mobile.matches ? opened : state === 'expanded'));
        const label = mobile.matches ? opened ? 'Tutup menu CMS' : 'Buka menu CMS' : state === 'expanded' ? 'Ciutkan sidebar' : 'Perluas sidebar';
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        sidebar.inert = mobile.matches && !opened;
        main.inert = mobile.matches && opened;
        backdrop.hidden = !mobile.matches || !opened;
        if (mobile.matches && opened) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        tooltip.hidden = true;
    }
    function close(restoreFocus = true) {
        if (!opened) return;
        opened = false;
        document.body.style.overflow = previousOverflow;
        render();
        if (restoreFocus) toggle.focus();
    }
    listen(toggle, 'click', () => {
        if (mobile.matches) {
            if (opened) return close();
            opened = true;
            previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            render();
            sidebar.querySelector('a')?.focus();
        } else {
            preference = document.documentElement.dataset.cmsSidebar === 'expanded' ? 'collapsed' : 'expanded';
            try { localStorage.setItem('cms-sidebar-state', preference); } catch { /* Keep the current session usable. */ }
            render();
        }
    });
    listen(backdrop, 'click', () => close());
    listen(shell.querySelector('[data-sidebar-close]'), 'click', () => close());
    listen(sidebar, 'click', event => { if (event.target.closest('a')) close(false); });
    listen(document, 'keydown', event => {
        if (!opened) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const links = [...sidebar.querySelectorAll('a, button')].filter(node => node.getClientRects().length);
            const first = links[0], last = links.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    const resize = () => { close(false); render(); };
    listen(mobile, 'change', resize);
    listen(compact, 'change', resize);
    listen(window, 'storage', event => { if (event.key === 'cms-sidebar-state') { preference = event.newValue; render(); } });
    sidebar.querySelectorAll('.cms-nav').forEach(link => {
        const show = () => {
            if (mobile.matches || document.documentElement.dataset.cmsSidebar !== 'collapsed') return;
            tooltip.textContent = link.getAttribute('aria-label');
            tooltip.hidden = false;
            const rect = link.getBoundingClientRect();
            tooltip.style.left = `${sidebar.getBoundingClientRect().right + 8}px`;
            tooltip.style.top = `${Math.max(8, Math.min(rect.top, innerHeight - tooltip.offsetHeight - 8))}px`;
        };
        listen(link, 'mouseenter', show);
        listen(link, 'focus', show);
        listen(link, 'mouseleave', () => { tooltip.hidden = true; });
        listen(link, 'blur', () => { tooltip.hidden = true; });
    });
    listen(sidebar, 'scroll', () => { tooltip.hidden = true; });
    render();
    return { destroy() { close(false); abort.abort(); main.inert = false; } };
}

// One analyzer stays inside the form. Only its presentation changes.
export function initAnalyzerPresentation(form) {
    const workspace = form.closest('.admin-post-layout');
    const panel = form.querySelector('[data-seo-analysis]');
    const launch = workspace.querySelector('[data-analysis-open]');
    const closeButton = panel.querySelector('[data-analysis-close]');
    const abort = new AbortController();
    const listen = (node, event, callback) => node.addEventListener(event, callback, { signal: abort.signal });
    const mobile = matchMedia('(max-width: 1023px)');
    let wide;
    let savedOverflow;
    let modal = false;
    let frame;
    const actions = form.querySelector('.admin-post-actions');
    function fitHeight() {
        const actionHeight = actions.getBoundingClientRect().height;
        workspace.style.setProperty('--savebar-height', `${actionHeight}px`);
        if (wide) {
            const top = Math.max(16, panel.getBoundingClientRect().top);
            panel.style.setProperty('--analysis-available-height', `${Math.max(160, innerHeight - top - actionHeight - 24)}px`);
        }
    }
    function scheduleHeight() {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(fitHeight);
    }
    function close(restoreFocus = true) {
        if (wide || !panel.open) return;
        if (panel.matches(':popover-open')) panel.hidePopover();
        panel.close();
        panel.removeAttribute('popover');
        launch.setAttribute('aria-expanded', 'false');
        if (modal) document.body.style.overflow = savedOverflow;
        modal = false;
        if (restoreFocus) launch.focus({ preventScroll: true });
    }
    function resize() {
        const next = analyzerFits(workspace.getBoundingClientRect().width);
        if (next === wide) { scheduleHeight(); return; }
        const hadFocus = panel.contains(document.activeElement);
        close(false);
        panel.removeAttribute('open');
        wide = next;
        workspace.dataset.analyzerMode = wide ? 'inline' : 'drawer';
        panel.setAttribute('role', wide ? 'complementary' : 'dialog');
        panel.removeAttribute('aria-modal');
        if (wide) {
            panel.setAttribute('open', '');
            if (hadFocus) panel.querySelector('[data-analysis-tab][aria-selected="true"]')?.focus({ preventScroll: true });
        }
        else if (hadFocus) launch.focus({ preventScroll: true });
        fitHeight();
    }
    listen(launch, 'click', () => {
        if (wide) return;
        if (panel.open) return close();
        modal = mobile.matches;
        panel.setAttribute('aria-modal', String(modal));
        if (modal) {
            savedOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            panel.showModal();
        } else {
            // The top layer avoids container-query containment clipping a fixed drawer.
            panel.setAttribute('popover', 'manual');
            panel.show();
            panel.showPopover();
        }
        launch.setAttribute('aria-expanded', 'true');
        closeButton.focus({ preventScroll: true });
    });
    listen(closeButton, 'click', () => close());
    listen(panel, 'cancel', event => { event.preventDefault(); close(); });
    listen(document, 'keydown', event => {
        if (event.key === 'Escape' && !wide && panel.open && !document.querySelector('.editor-dialog[open]')) {
            event.preventDefault(); close();
        }
    });
    listen(panel, 'click', event => {
        if (!modal || event.target !== panel) return;
        const rect = panel.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) close();
    });
    // Result links must reveal the field before the existing analyzer focuses it.
    panel.addEventListener('click', event => {
        if (event.target.closest('[data-analysis-results] button')) close(false);
    }, { capture: true, signal: abort.signal });
    listen(mobile, 'change', () => close());
    listen(window, 'scroll', scheduleHeight);
    listen(window, 'resize', scheduleHeight);
    const observer = new ResizeObserver(resize);
    observer.observe(workspace);
    observer.observe(actions);
    resize();
    return { destroy() { close(false); cancelAnimationFrame(frame); observer.disconnect(); abort.abort(); } };
}
