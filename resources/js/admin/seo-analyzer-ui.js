import { analyzeArticle, extractContentFacts, groupStatus, validReference } from './seo-analyzer';

const labels = { good: 'Baik', info: 'Info', improvement: 'Perlu diperbaiki', problem: 'Masalah' };
const colors = { good: 'text-green-700 bg-green-50 border-green-200', info: 'text-slate-700 bg-slate-50 border-slate-200', improvement: 'text-amber-800 bg-amber-50 border-amber-200', problem: 'text-red-700 bg-red-50 border-red-200' };
const groups = { seo: 'SEO', readability: 'Readability', aeo: 'AEO' };
const formatter = new Intl.NumberFormat('id-ID');

export function initSeoAnalyzer(form) {
    const panel = form.querySelector('[data-seo-analysis]');
    if (!panel) return { destroy() {} };
    const abort = new AbortController();
    const listen = (target, event, callback) => target?.addEventListener(event, callback, { signal: abort.signal });
    const input = name => form.elements.namedItem(name);
    const value = name => input(name)?.value || '';
    const chosen = name => input(name)?.checked || false;
    const selected = name => [...(form.querySelector(`select[name="${name}"]`)?.selectedOptions || [])].filter(option => option.value).length;
    let active = 'seo';
    let timer;
    let cachedContent;
    let cachedSite;
    let cachedFacts;

    function readState() {
        const author = form.querySelector('[name="author_id"]');
        const authorOption = author?.options[author.selectedIndex];
        const references = [...form.querySelectorAll('[data-reference-row]')].filter(row => row.querySelector('[name$="[title]"]')?.value.trim()).map(row => row.querySelector('[name$="[url]"]')?.value.trim()).filter(validReference);
        return {
            siteUrl: panel.dataset.siteUrl, title: value('title'), slug: value('slug'), excerpt: value('excerpt'), content: value('content'),
            seoTitle: value('seo_title'), seoDescription: value('seo_description'), focusKeyphrase: value('focus_keyphrase'),
            featuredImage: value('featured_image'), featuredAlt: value('featured_image_alt'), ogImage: value('og_image'),
            directAnswer: value('direct_answer'), keyTakeaways: value('key_takeaways'), category: value('category_id'),
            author: value('author_id'), authorBio: authorOption?.dataset.authorBio === '1', topicCount: selected('topics[]'),
            sourceCount: references.length, references, authorFallback: panel.dataset.authorFallback,
            postExists: panel.dataset.postExists === '1', savedDate: panel.dataset.savedDate, publishedDate: panel.dataset.publishedDate, scheduledDate: value('scheduled_at'),
            noindex: chosen('noindex'), status: value('status'), contentType: value('content_type'),
        };
    }

    function element(tag, className = '', content = '') {
        const node = document.createElement(tag);
        node.className = className;
        node.textContent = content;
        return node;
    }

    function showRules(results) {
        const container = panel.querySelector('[data-analysis-results]');
        container.replaceChildren();
        for (const status of ['problem', 'improvement', 'info', 'good']) {
            const rows = results.filter(row => row.group === active && row.status === status);
            if (!rows.length) {
                if (status !== 'info') container.append(element('p', `rounded-lg border px-3 py-1.5 text-xs ${colors[status]}`, `✓ ${labels[status]} (0)`));
                continue;
            }
            const details = document.createElement('details');
            details.className = `rounded-lg border ${colors[status]}`;
            details.open = status === 'problem' || status === 'improvement' || status === 'info';
            const summary = element('summary', 'cursor-pointer px-3 py-2 text-sm font-semibold', `${labels[status]} (${rows.length})`);
            details.append(summary);
            const list = element('ul', 'space-y-1 border-t border-current/10 p-2');
            for (const row of rows) {
                const item = element('li', 'rounded-md bg-white/70 p-2 text-xs');
                const target = row.target === 'references' ? form.querySelector('[data-references] [name$="[url]"]') || form.querySelector('[data-reference-add]') : row.target ? form.elements.namedItem(row.target) || form.querySelector(`[name="${row.target}"]`) : null;
                const heading = element(target ? 'button' : 'strong', target ? 'block text-left font-semibold underline-offset-2 hover:underline focus:underline' : 'block font-semibold', row.title);
                if (target) {
                    heading.type = 'button';
                    heading.addEventListener('click', () => {
                        const focus = row.target === 'content' ? form.querySelector('[data-article-editor]') : target;
                        focus?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        focus?.focus({ preventScroll: true });
                    });
                }
                item.append(heading, element('p', 'mt-1 text-slate-700', row.message));
                list.append(item);
            }
            details.append(list);
            container.append(details);
        }
    }

    function render() {
        if (!panel.isConnected) return;
        const state = readState();
        if (state.content !== cachedContent || state.siteUrl !== cachedSite) {
            cachedContent = state.content;
            cachedSite = state.siteUrl;
            cachedFacts = extractContentFacts(state.content, state.siteUrl);
        }
        const analysis = analyzeArticle(state, cachedFacts);
        const summary = panel.querySelector('[data-analysis-summary]');
        summary.replaceChildren();
        for (const [group, label] of Object.entries(groups)) {
            const status = groupStatus(analysis.results.filter(row => row.group === group));
            const badge = element('div', `rounded-lg border px-1 py-2 ${colors[status]}`);
            badge.append(element('strong', 'block', label), element('span', 'block', labels[status]));
            summary.append(badge);
        }
        panel.querySelector('[data-analysis-stats]').textContent = `${formatter.format(analysis.facts.wordCount)} kata · ±${Math.ceil(analysis.facts.wordCount / 200)} menit baca`;
        const notice = panel.querySelector('[data-analysis-notice]');
        notice.hidden = !analysis.noindex && !!state.focusKeyphrase.trim();
        notice.textContent = analysis.noindex ? 'Artikel disetel noindex. Analisis konten tetap tersedia; pemeriksaan keyphrase untuk indeks pencarian disembunyikan.' : 'Tambahkan Focus Keyphrase untuk mengaktifkan analisis keyphrase.';
        panel.querySelector('[data-analysis-preview-title]').textContent = analysis.preview.title;
        panel.querySelector('[data-analysis-preview-url]').textContent = `${state.siteUrl.replace(/\/$/, '')}/${analysis.preview.slug || 'slug-artikel'}`;
        panel.querySelector('[data-analysis-preview-description]').textContent = analysis.preview.description;
        form.querySelector('[data-seo-title-meter]').textContent = `${analysis.titleLength} / 60 karakter termasuk nama situs`;
        form.querySelector('[data-seo-description-meter]').textContent = `${analysis.descriptionLength} / 160 karakter dengan fallback halaman publik`;
        showRules(analysis.results);
    }

    function schedule(event) {
        clearTimeout(timer);
        timer = setTimeout(render, event?.target?.name === 'content' ? 320 : 0);
    }
    listen(form, 'input', schedule);
    listen(form, 'change', schedule);
    listen(form, 'seo:state-change', schedule);
    const tabs = [...panel.querySelectorAll('[data-analysis-tab]')];
    function activate(tab) {
        active = tab.dataset.analysisTab;
        tabs.forEach(button => {
            button.setAttribute('aria-selected', String(button === tab));
            button.classList.toggle('border-b-2', button === tab);
            button.classList.toggle('border-indigo-600', button === tab);
            button.classList.toggle('text-indigo-700', button === tab);
        });
        panel.querySelector('[data-analysis-results]').setAttribute('aria-labelledby', tab.id);
        render();
    }
    tabs.forEach((tab, index) => {
        listen(tab, 'click', () => activate(tab));
        listen(tab, 'keydown', event => {
            const next = event.key === 'ArrowRight' ? (index + 1) % tabs.length : event.key === 'ArrowLeft' ? (index + tabs.length - 1) % tabs.length : event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : null;
            if (next === null) return;
            event.preventDefault();
            tabs[next].focus();
            activate(tabs[next]);
        });
    });
    activate(tabs[0]);
    return { destroy() { clearTimeout(timer); abort.abort(); } };
}
