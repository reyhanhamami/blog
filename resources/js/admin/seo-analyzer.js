// Guidance derived only from the current form. No network request or persisted score.
import { headingStructure } from './heading-structure.js';
const words = value => (String(value || '').match(/[\p{L}\p{N}]+(?:['’][\p{L}\p{N}]+)*/gu) || []);
export const normalizeKeyphrase = value => String(value || '').toLocaleLowerCase('id').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^\p{L}\p{N}]+/gu, ' ').trim().replace(/\s+/g, ' ');
const normal = normalizeKeyphrase;
const includesPhrase = (text, phrase) => !!phrase && ` ${normal(text)} `.includes(` ${normal(phrase)} `);
export const countKeyphrase = (text, phrase) => {
    const tokens = normal(text).split(' ');
    const needle = normal(phrase).split(' ');
    if (!needle[0]) return 0;
    let total = 0;
    for (let index = 0; index <= tokens.length - needle.length; index++) {
        if (needle.every((word, offset) => tokens[index + offset] === word)) total++;
    }
    return total;
};
const countPhrase = countKeyphrase;
const count = value => words(value).length;
export const validReference = value => {
    try { return ['http:', 'https:'].includes(new URL(String(value || '').trim()).protocol); }
    catch { return false; }
};
export function sentenceLengths(units) {
    return units.flatMap(unit => String(unit || '')
        .replace(/https?:\/\/[^\s<>]+/gu, match => `URL${match.match(/[.!?]+$/u)?.[0] || ''}`)
        .replace(/(?<=\d)\.(?=\d)/gu, '')
        .split(/[.!?]+(?:\s+|$)|\n+/u)
        .map(value => count(value)).filter(Boolean));
}
export function resolvedDescription(state, facts) {
    const explicit = state.seoDescription?.trim() || state.excerpt?.trim();
    if (explicit) return explicit;
    // Mirrors public/post.blade.php: seo_description → excerpt → content_plain/strip_tags(content) → title.
    const source = facts.plainText?.trim() || state.title?.trim() || '';
    return [...source].length > 155 ? [...source].slice(0, 155).join('') + '...' : source;
}

export function classifyLink(href, siteUrl) {
    const raw = String(href || '').trim();
    if (!raw || raw.startsWith('#') || raw.startsWith('?')) return null;
    try {
        const site = siteUrl instanceof URL ? siteUrl : new URL(siteUrl);
        const url = new URL(raw, site);
        if (!['http:', 'https:'].includes(url.protocol)) return null;
        return url.host === site.host ? 'internal' : 'external';
    } catch { return null; }
}

export function extractContentFacts(html, siteUrl) {
    const document = new DOMParser().parseFromString(String(html || ''), 'text/html');
    const plainText = document.body.textContent.trim();
    document.querySelectorAll('script,style,template,noscript,pre,code').forEach(node => node.remove());
    const links = { internal: 0, external: 0 };
    const site = new URL(siteUrl);
    document.querySelectorAll('a[href]').forEach(node => {
        const kind = classifyLink(node.getAttribute('href'), site);
        if (kind) links[kind]++;
    });
    const images = [...document.querySelectorAll('img')].map(node => ({ alt: node.getAttribute('alt')?.trim() || '' }));
    document.querySelectorAll('figcaption,caption,table').forEach(node => node.remove());
    document.body.querySelectorAll('p,li,h1,h2,h3,h4,h5,h6,blockquote,br').forEach(node => node.after(document.createTextNode(' ')));
    const text = document.body.textContent.replace(/\s+/g, ' ').trim();
    const headings = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map(node => ({ level: Number(node.tagName[1]), text: node.textContent.trim() }));
    const paragraphs = [...document.querySelectorAll('p')].filter(node => !node.closest('figure')).map(node => node.textContent.trim()).filter(Boolean);
    const readableUnits = [...document.querySelectorAll('p,li,blockquote')].filter(node => !node.parentElement?.closest('p,li,blockquote')).map(node => node.textContent.trim()).filter(Boolean);
    const unwrappedBody = document.body.cloneNode(true);
    unwrappedBody.querySelectorAll('h1,h2,h3,h4,h5,h6').forEach(node => node.remove());
    const fallbackProse = unwrappedBody.textContent.replace(/\s+/g, ' ').trim();
    const intro = paragraphs[0] || readableUnits[0] || words(fallbackProse).slice(0, 150).join(' ');
    const sentences = sentenceLengths(readableUnits.length ? readableUnits : [fallbackProse]);
    const longSections = [];
    let sectionWords = 0;
    for (const node of document.body.querySelectorAll('h2,h3,p,li,blockquote')) {
        if (/^H[23]$/.test(node.tagName)) { longSections.push(sectionWords); sectionWords = 0; }
        else if (!node.parentElement?.closest('p,li,blockquote')) sectionWords += count(node.textContent);
    }
    longSections.push(sectionWords);
    return { text, plainText, headings, paragraphs, intro, links, images, sentences, longSections, hasList: !!document.querySelector('ul,ol'), wordCount: count(text) };
}

export function analyzeArticle(state, facts = extractContentFacts(state.content, state.siteUrl)) {
    const results = [];
    const add = (id, group, status, title, message, target = '', priority = 0) => results.push({ id, group, status, title, message, target, priority });
    const phrase = normal(state.focusKeyphrase);
    const title = (state.seoTitle?.trim() || state.title?.trim() || '');
    const renderedTitle = title ? (title.endsWith(' | Besofton Insights') ? title : `${title} | Besofton Insights`) : '';
    const description = resolvedDescription(state, facts);
    const titleLength = [...renderedTitle].length;
    const descriptionLength = [...description].length;
    const slug = state.slug?.trim() || normal(state.title).replaceAll(' ', '-');
    const indexable = !state.noindex;

    if (indexable) {
        add('seo.title', 'seo', title ? 'good' : 'problem', 'Judul hasil pencarian', title ? (state.seoTitle ? 'Judul SEO khusus tersedia.' : 'Judul artikel digunakan sebagai fallback SEO.') : 'Isi judul artikel agar halaman punya judul.', 'seo_title', 3);
        if (title) add('seo.title_length', 'seo', titleLength >= 30 && titleLength <= 60 ? 'good' : 'improvement', 'Panjang judul SEO', `${titleLength} karakter termasuk nama situs; kisaran 30–60 karakter hanya panduan.`, 'seo_title');
        add('seo.description', 'seo', description ? 'good' : 'problem', 'Deskripsi hasil pencarian', state.seoDescription ? 'Deskripsi SEO khusus tersedia.' : description ? 'Halaman publik memakai ringkasan, isi artikel, atau judul sebagai fallback.' : 'Tambahkan deskripsi SEO atau ringkasan.', 'seo_description', 3);
        if (description) add('seo.description_length', 'seo', descriptionLength >= 120 && descriptionLength <= 160 ? 'good' : 'improvement', 'Panjang deskripsi', `${descriptionLength} karakter; kisaran 120–160 karakter hanya panduan.`, 'seo_description');
        add('seo.slug', 'seo', !slug ? 'problem' : slug.length > 75 || /--/.test(slug) ? 'improvement' : 'good', 'Slug artikel', !slug ? 'Isi judul atau slug artikel.' : slug.length > 75 ? 'Slug cukup panjang. Pertimbangkan versi yang lebih ringkas.' : 'Slug tersedia dan mudah dibaca.', 'slug');
        if (phrase) {
            for (const [id, value, label, target] of [
                ['title_phrase', title, 'Keyphrase pada judul SEO', 'seo_title'],
                ['slug_phrase', slug, 'Keyphrase pada slug', 'slug'],
                ['description_phrase', description, 'Keyphrase pada deskripsi', 'seo_description'],
                ['intro_phrase', facts.intro, 'Keyphrase pada pembuka', 'content'],
                ['heading_phrase', facts.headings.filter(h => [2, 3].includes(h.level)).map(h => h.text).join(' '), 'Keyphrase pada H2/H3', 'content'],
            ]) add(`seo.${id}`, 'seo', includesPhrase(value, phrase) ? 'good' : 'improvement', label, includesPhrase(value, phrase) ? 'Frasa ditemukan secara natural.' : 'Pertimbangkan memakai frasa ini secara natural bila relevan.', target);
            if (facts.wordCount) {
                const occurrences = countPhrase(facts.text, phrase);
                const density = occurrences / facts.wordCount * 100;
                const densityMessage = !occurrences ? 'Focus keyphrase belum ditemukan dalam isi artikel. Gunakan secara natural jika sesuai dengan topik.' : density > 2.5 ? `Penggunaan focus keyphrase terlalu sering (${occurrences} kemunculan dalam ${facts.wordCount} kata, ${density.toFixed(1)}%). Hindari pengulangan yang dipaksakan.` : `${occurrences} kemunculan dalam ${facts.wordCount} kata (${density.toFixed(1)}%). Gunakan secara natural jika relevan.`;
                add('seo.density', 'seo', !occurrences || density > 2.5 ? 'problem' : density < 0.5 ? 'improvement' : 'good', 'Penggunaan keyphrase', densityMessage, 'content', 2);
            }
        }
    }
    if (facts.wordCount) {
        add('seo.internal_links', 'seo', facts.links.internal ? 'good' : 'improvement', 'Tautan internal', facts.links.internal ? `${facts.links.internal} tautan ke situs ini ditemukan.` : 'Tambahkan tautan ke artikel Besofton lain yang relevan.', 'content');
        add('seo.external_links', 'seo', facts.links.external || state.sourceCount ? 'good' : 'improvement', 'Sumber eksternal', facts.links.external || state.sourceCount ? `${facts.links.external} tautan keluar dan ${state.sourceCount} referensi valid.` : 'Pertimbangkan sumber eksternal tepercaya jika relevan.', facts.links.external ? 'content' : 'references');
    }
    const missingAlt = facts.images.filter(image => !image.alt).length;
    if (facts.images.length || state.featuredImage) add('seo.image_alt', 'seo', missingAlt ? 'problem' : state.featuredImage && !state.featuredAlt ? 'improvement' : 'good', 'Alt gambar', missingAlt ? `${missingAlt} gambar dalam isi artikel belum memiliki alt text.` : state.featuredImage && !state.featuredAlt ? 'Gambar unggulan memakai judul artikel sebagai fallback alt; tambahkan deskripsi gambar yang lebih tepat.' : 'Semua gambar memiliki alt text.', !missingAlt && state.featuredImage && !state.featuredAlt ? 'featured_image_alt' : 'content');
    if (facts.wordCount > 600 && !facts.images.length && !state.featuredImage) add('seo.images', 'seo', 'improvement', 'Ilustrasi artikel', 'Artikel panjang dapat terbantu oleh gambar yang relevan.', 'featured_image');
    add('seo.featured_image', 'seo', state.featuredImage ? 'good' : 'improvement', 'Gambar unggulan', state.featuredImage ? 'Gambar unggulan tersedia.' : 'Pertimbangkan gambar unggulan yang relevan.', 'featured_image');
    add('seo.og_image', 'seo', state.ogImage || state.featuredImage ? 'good' : 'improvement', 'OG image', state.ogImage ? 'OG image khusus tersedia.' : state.featuredImage ? 'Gambar unggulan menjadi fallback OG image.' : 'Belum ada gambar untuk pratinjau sosial.', 'og_image');
    add('seo.category', 'seo', state.category ? 'good' : 'improvement', 'Kategori', state.category ? 'Kategori dipilih.' : 'Pilih kategori yang paling sesuai.', 'category_id');
    if (indexable) add('seo.topic', 'seo', state.topicCount ? 'good' : 'improvement', 'Topik', state.topicCount ? `${state.topicCount} topik dipilih.` : 'Topik dapat menghubungkan artikel dengan kelompok konten terkait.', 'topics[]');

    if (facts.wordCount) {
        add('readability.length', 'readability', facts.wordCount < 300 && !['opinion', 'news'].includes(state.contentType) ? 'improvement' : 'good', 'Panjang artikel', facts.wordCount < 300 ? 'Artikel cukup singkat. Pastikan pembahasan sudah menjawab intent pembaca.' : `${facts.wordCount} kata dalam artikel.`, 'content');
        const structure = headingStructure(facts.headings);
        const bodyH1 = structure.bodyH1Count > 0;
        add('readability.body_h1', 'readability', bodyH1 ? 'improvement' : 'good', 'H1 pada isi artikel', bodyH1 ? 'Judul publik sudah menjadi H1. Gunakan H2 untuk bagian utama.' : 'Tidak ada H1 tambahan pada isi artikel.', 'content');
        const levels = facts.headings.map(h => h.level);
        const skipped = structure.skipped.length > 0;
        add('readability.headings', 'readability', skipped || !levels.includes(2) ? 'improvement' : 'good', 'Hierarki heading', skipped ? 'Ada lompatan tingkat heading. Periksa urutan H2/H3.' : !levels.includes(2) ? 'Tambahkan H2 untuk bagian utama jika artikel memerlukannya.' : 'Hierarki heading tersusun.', 'content');
        const longParagraphs = facts.paragraphs.filter(p => count(p) > 120).length;
        add('readability.paragraphs', 'readability', longParagraphs ? 'improvement' : 'good', 'Panjang paragraf', longParagraphs ? `${longParagraphs} paragraf melebihi sekitar 120 kata.` : 'Paragraf mudah dipindai.', 'content');
        const longSentences = facts.sentences.filter(length => length > 30).length;
        const ratio = facts.sentences.length ? longSentences / facts.sentences.length : 0;
        add('readability.sentences', 'readability', ratio > 0.3 ? 'improvement' : 'good', 'Panjang kalimat', ratio > 0.3 ? `${Math.round(ratio * 100)}% kalimat melebihi sekitar 30 kata; pemeriksaan ini perkiraan.` : 'Sebagian besar kalimat cukup ringkas.', 'content');
        add('readability.subheadings', 'readability', facts.wordCount > 600 && (facts.longSections.some(length => length > 300) || !levels.some(level => [2, 3].includes(level))) ? 'improvement' : 'good', 'Sebaran subheading', facts.wordCount > 600 && facts.longSections.some(length => length > 300) ? 'Ada bagian panjang tanpa H2/H3; pertimbangkan pemecahan.' : 'Sebaran subheading memadai untuk panjang artikel.', 'content');
        if (facts.wordCount > 600 && ['tutorial', 'guide'].includes(state.contentType)) add('readability.lists', 'readability', facts.hasList ? 'good' : 'improvement', 'Daftar langkah', facts.hasList ? 'Daftar membantu pemindaian tutorial.' : 'Jika ada langkah atau rangkuman, daftar dapat membantu pembaca.', 'content');
    } else add('readability.content', 'readability', 'problem', 'Isi artikel', 'Tulis konten artikel untuk melihat analisis keterbacaan.', 'content', 3);

    const answerWords = count(state.directAnswer);
    add('aeo.direct_answer', 'aeo', !answerWords || answerWords > 80 ? 'improvement' : 'good', 'Direct Answer', !answerWords ? 'Tulis jawaban ringkas untuk pertanyaan utama bila sesuai.' : answerWords > 80 ? `${answerWords} kata. Usahakan jawaban lebih ringkas, sekitar 30–80 kata.` : `${answerWords} kata dalam jawaban langsung.`, 'direct_answer');
    const takeaways = String(state.keyTakeaways || '').split(/\r?\n/).filter(value => value.trim()).length;
    if (facts.wordCount >= 300 || takeaways) add('aeo.takeaways', 'aeo', takeaways ? 'good' : 'improvement', 'Key Takeaways', takeaways ? `${takeaways} poin tersedia; 3–7 poin umumnya mudah dipindai.` : 'Tambahkan poin inti bila membantu pembaca.', 'key_takeaways');
    if (!['opinion'].includes(state.contentType) || state.sourceCount) add('aeo.references', 'aeo', state.sourceCount ? 'good' : facts.links.external ? 'info' : 'improvement', 'Referensi', state.sourceCount ? `${state.sourceCount} URL referensi valid tersedia.` : facts.links.external ? 'Tautan sumber eksternal sudah ada di isi artikel; referensi terpisah bersifat opsional.' : 'Tambahkan referensi bila artikel memuat klaim faktual.', 'references');
    add('aeo.author', 'aeo', !state.author ? 'improvement' : state.authorBio ? 'good' : 'improvement', 'Profil penulis', !state.author ? `Halaman memakai ${state.authorFallback || 'Tim Besofton'}; pilih profil penulis untuk atribusi yang lebih jelas.` : state.authorBio ? 'Nama dan bio penulis tersedia.' : 'Penulis dipilih; bio profil penulis masih kosong.', 'author_id');
    add('aeo.canonical', 'aeo', slug ? 'good' : 'improvement', 'Canonical', slug ? 'URL artikel otomatis menjadi canonical.' : 'Canonical otomatis tersedia setelah slug artikel ditentukan.', 'slug');
    const schemaMissing = !state.title?.trim() ? ['Judul artikel belum tersedia.', 'title'] : !description ? ['Deskripsi artikel belum tersedia.', 'seo_description'] : !slug ? ['Slug artikel belum tersedia untuk canonical.', 'slug'] : null;
    if (schemaMissing) add('aeo.schema', 'aeo', 'improvement', 'Article schema', schemaMissing[0], schemaMissing[1]);
    else if (state.status === 'published' && state.postExists && !state.publishedDate) add('aeo.schema', 'aeo', 'problem', 'Article schema', 'Artikel published belum mempunyai tanggal terbit.', 'status', 2);
    else if (state.status === 'published' && !state.postExists) add('aeo.schema', 'aeo', 'info', 'Article schema', 'Tanggal terbit akan dicatat saat artikel dipublikasikan.');
    else if (state.status === 'scheduled') add('aeo.schema', 'aeo', state.scheduledDate ? 'info' : 'improvement', 'Article schema', state.scheduledDate ? 'Article schema akan memakai tanggal jadwal setelah artikel dipublikasikan.' : 'Jadwal terbit belum ditentukan.', state.scheduledDate ? '' : 'scheduled_at');
    else if (state.status !== 'published') add('aeo.schema', 'aeo', 'info', 'Article schema', 'Article schema akan menggunakan tanggal terbit setelah artikel dipublikasikan.');
    else add('aeo.schema', 'aeo', 'good', 'Article schema', 'Article schema siap: judul, deskripsi, penulis, tanggal terbit, dan canonical tersedia. Gambar bersifat opsional.');
    if (state.savedDate) add('aeo.updated', 'aeo', 'good', 'Tanggal perubahan', 'Halaman publik memakai tanggal perubahan artikel yang sebenarnya.');
    return { results, facts, preview: { title: renderedTitle || 'Judul artikel', description: description || 'Deskripsi artikel akan tampil di sini.', slug }, titleLength, descriptionLength, noindex: state.noindex };
}

export const groupStatus = results => results.some(result => result.status === 'problem' && result.priority >= 2) ? 'problem' : results.some(result => ['problem', 'improvement'].includes(result.status)) ? 'improvement' : results.some(result => result.status === 'info') ? 'info' : 'good';
