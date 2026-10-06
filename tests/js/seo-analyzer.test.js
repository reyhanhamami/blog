import test from 'node:test';
import assert from 'node:assert/strict';
import { normalizeKeyphrase, countKeyphrase, classifyLink, groupStatus } from '../../resources/js/admin/seo-analyzer.js';
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
