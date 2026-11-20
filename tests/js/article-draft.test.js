import test from 'node:test';
import assert from 'node:assert/strict';
import { articleDraft } from '../../resources/js/article-draft.js';

test('article body recovery is user/path scoped, expires, and clears after confirmed save', () => {
    const values = new Map();
    const storage = { getItem: key => values.get(key), setItem: (key,value) => values.set(key,value), removeItem: key => values.delete(key) };
    let now = 100;
    const draft = articleDraft('1', '/admin/posts/5/edit', () => storage, () => now);
    assert.equal(draft.write('<h2>Belum disimpan</h2>'), true);
    assert.equal(draft.read(), '<h2>Belum disimpan</h2>');
    assert.equal(articleDraft('2', '/admin/posts/5/edit', () => storage).read(), null);
    assert.equal(articleDraft('1', '/admin/posts/create', () => storage).read(), null);
    now += 86400001;
    assert.equal(draft.read(), null);
    assert.equal(values.size, 0);
    draft.write('Sudah disimpan');
    draft.remove();
    assert.equal(draft.read(), null);
});

test('blocked or corrupt browser storage never interrupts editing or submission', () => {
    const blocked = articleDraft('1', '/edit', () => { throw new Error('Storage blocked'); });
    assert.equal(blocked.write('Konten'), false);
    assert.equal(blocked.read(), null);
    assert.doesNotThrow(() => blocked.remove());
    const corrupt = articleDraft('1', '/edit', () => ({ getItem: () => '{invalid' }));
    assert.equal(corrupt.read(), null);
    assert.equal(blocked.write('x'.repeat(1000001)), false);
});
