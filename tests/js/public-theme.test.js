import test from 'node:test';
import assert from 'node:assert/strict';
import { contrastRatio, validColor } from '../../resources/js/public-theme.js';

test('native HEX inputs accept only safe six digit values', () => {
    assert.equal(validColor('#12ABef'), true);
    for (const value of ['#abc', '#12345678', '#123456; color:red', 'red', 'var(--x)', null]) assert.equal(validColor(value), false);
});
test('preview contrast uses sRGB ratio, independent of color order', () => {
    assert.equal(contrastRatio('#ffffff', '#000000'), 21);
    assert.equal(contrastRatio('#000000', '#ffffff'), 21);
    assert.equal(contrastRatio('#e8af4b', '#e8af4b'), 1);
    assert.ok(contrastRatio('#ffffff', '#e8af4b') < 3);
    assert.ok(contrastRatio('#0a0a0a', '#f7f3eb') > 17);
});
