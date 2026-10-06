import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeKeyphrase, countKeyphrase, classifyLink, groupStatus, sentenceLengths, resolvedDescription, validReference, analyzeArticle } from '../../resources/js/admin/seo-analyzer.js';
import { headingStructure } from '../../resources/js/admin/heading-structure.js';

test('normalizes Indonesian focus keyphrases across case, punctuation, and hyphens', () => {
    assert.equal(normalizeKeyphrase('  Prompt AI-untuk Coding!  '), 'prompt ai untuk coding');
    assert.equal(normalizeKeyphrase('cara-membuat-prompt-ai-untuk-coding'), 'cara membuat prompt ai untuk coding');
});

test('counts repeated whole phrases without matching word fragments', () => {
    assert.equal(countKeyphrase('Prompt AI. prompt-AI untuk coding; prompt ai.', 'prompt ai'), 3);
    assert.equal(countKeyphrase('pemrograman dan programmer', 'program'), 0);
    assert.equal(countKeyphrase('apa saja', ''), 0);
});

test('a critical problem controls the summary while lesser suggestions remain guidance', () => {
    assert.equal(groupStatus([{ status: 'problem', priority: 2 }]), 'problem');
    assert.equal(groupStatus([{ status: 'problem', priority: 0 }]), 'improvement');
    assert.equal(groupStatus([{ status: 'good' }]), 'good');
    assert.equal(groupStatus([{ status: 'info' }]), 'info');
});

const facts = (text = '') => ({ text, plainText: text, headings: [], paragraphs: text ? [text] : [], intro: text, links: { internal: 0, external: 0 }, images: [], sentences: sentenceLengths([text]), longSections: [text.split(/\s+/).length], hasList: false, wordCount: text.trim() ? text.trim().split(/\s+/).length : 0 });
const state = overrides => ({ siteUrl: 'https://besofton.id', title: 'Cara Deploy Docker', slug: 'cara-deploy-docker', excerpt: 'Pelajari cara deploy Docker ke server production dengan aman.', seoTitle: '', seoDescription: '', focusKeyphrase: '', featuredImage: '', featuredAlt: '', ogImage: '', directAnswer: '', keyTakeaways: '', category: '', author: '', authorFallback: 'Tim Besofton', authorBio: false, topicCount: 0, sourceCount: 0, status: 'draft', postExists: false, savedDate: '', publishedDate: '', scheduledDate: '', contentType: 'tutorial', noindex: false, ...overrides });
const rule = (analysis, id) => analysis.results.find(item => item.id === id);

test('public description and title fallbacks count as ready without hiding length guidance', () => {
    const analysis = analyzeArticle(state(), facts('Artikel ini menjelaskan deployment.'));
    assert.equal(rule(analysis, 'seo.title').status, 'good');
    assert.equal(rule(analysis, 'seo.description').status, 'good');
    assert.equal(resolvedDescription(state({ excerpt: '' }), facts('Isi artikel.')), 'Isi artikel.');
    assert.equal(rule(analysis, 'aeo.canonical').status, 'good');
});

test('draft and scheduled schema dates are informational, while a saved published article needs its date', () => {
    assert.equal(rule(analyzeArticle(state(), facts()), 'aeo.schema').status, 'info');
    assert.equal(rule(analyzeArticle(state({ status: 'scheduled', scheduledDate: '2026-10-10T09:00' }), facts()), 'aeo.schema').status, 'info');
    assert.equal(rule(analyzeArticle(state({ status: 'published', postExists: true }), facts()), 'aeo.schema').message, 'Artikel published belum mempunyai tanggal terbit.');
    assert.equal(rule(analyzeArticle(state({ status: 'published', postExists: true, publishedDate: '2026-10-06T00:00:00Z' }), facts()), 'aeo.schema').status, 'good');
});

test('valid references satisfy both source rules and content links can stand alone', () => {
    assert.equal(validReference('https://docs.docker.com/'), true);
    for (const unsafe of ['javascript:alert(1)', 'data:text/html,a', 'file:///tmp/x']) assert.equal(validReference(unsafe), false);
    const withReference = analyzeArticle(state({ sourceCount: 1 }), facts('Artikel dengan sumber.'));
    assert.equal(rule(withReference, 'seo.external_links').status, 'good');
    assert.equal(rule(withReference, 'aeo.references').status, 'good');
    const linked = facts('Artikel dengan tautan.'); linked.links.external = 1;
    assert.equal(rule(analyzeArticle(state(), linked), 'aeo.references').status, 'info');
});

test('zero keyphrase stays a problem and its message does not warn about overuse', () => {
    const analysis = analyzeArticle(state({ focusKeyphrase: 'erge' }), facts('Docker mempermudah deployment. Container menjaga lingkungan tetap konsisten.'));
    assert.equal(rule(analysis, 'seo.density').status, 'problem');
    assert.match(rule(analysis, 'seo.density').message, /belum ditemukan/);
    assert.doesNotMatch(rule(analysis, 'seo.density').message, /pengulangan yang dipaksakan/);
    assert.equal(rule(analysis, 'seo.title_phrase').status, 'improvement');
});

test('a relevant focus phrase passes title, slug, description, intro, and H2 placement', () => {
    const content = `Cara deploy Docker ke server. ${'Langkah ini menjaga aplikasi tetap stabil. '.repeat(30)}`;
    const articleFacts = facts(content);
    articleFacts.headings = [{ level: 2, text: 'Cara Deploy Docker ke Server' }];
    const analysis = analyzeArticle(state({ focusKeyphrase: 'cara deploy Docker', seoTitle: 'Cara Deploy Docker ke Server Production' }), articleFacts);
    for (const id of ['seo.title_phrase', 'seo.slug_phrase', 'seo.description_phrase', 'seo.intro_phrase', 'seo.heading_phrase', 'seo.density']) {
        assert.equal(rule(analysis, id).status, 'good', id);
    }
});

test('Indonesian punctuation makes three sentences; URLs, versions, and decimals stay intact', () => {
    assert.equal(sentenceLengths(['Docker mempermudah deployment. Container menjaga lingkungan tetap konsisten. Aplikasi kemudian dapat dijalankan pada server.']).length, 3);
    assert.equal(sentenceLengths(['Buka https://laravel.com/docs/12.x. Versi 1.2 tetap valid!']).length, 2);
    assert.deepEqual(sentenceLengths(['Kalimat pertama.', 'Kalimat kedua.']).length, 2);
});

test('heading hierarchy respects the public H1 and reports body H1 and skipped levels', () => {
    const result = headingStructure([{ level: 1, text: 'Duplikat' }, { level: 2, text: 'Bagian' }, { level: 4, text: 'Lompatan' }]);
    assert.equal(result.bodyH1Count, 1);
    assert.deepEqual(result.skipped, [{ from: 2, to: 4, text: 'Lompatan' }]);
});

test('classifies site links and ignores non-web and fragment links', () => {
    const site = 'https://blog.besofton.id';
    assert.equal(classifyLink('/artikel-lain', site), 'internal');
    assert.equal(classifyLink('https://blog.besofton.id/topik', site), 'internal');
    assert.equal(classifyLink('https://example.org/research', site), 'external');
    assert.equal(classifyLink('mailto:tim@example.org', site), null);
    assert.equal(classifyLink('javascript:alert(1)', site), null);
    assert.equal(classifyLink('#ringkasan', site), null);
});
