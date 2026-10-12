// Run against an isolated QA database, with PLAYWRIGHT_MODULE pointing to an
// existing playwright-core module. No browser dependency is added to the CMS.
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const modulePath = process.env.PLAYWRIGHT_MODULE;
if (!modulePath) throw new Error('Set PLAYWRIGHT_MODULE to playwright-core/index.mjs');
const { chromium } = await import(pathToFileURL(modulePath).href);
const baseURL = process.env.CMS_QA_URL || 'http://127.0.0.1:8000';
if (!['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) throw new Error('Use an isolated local QA server.');
const browser = await chromium.launch({ headless: true, ...(process.env.CMS_QA_CHROMIUM ? { executablePath: process.env.CMS_QA_CHROMIUM } : {}) });
const context = await browser.newContext({ baseURL });
const offlineFonts = process.env.CMS_QA_OFFLINE_FONTS === '1';
if (offlineFonts) await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '/* External fonts unavailable in the offline QA environment. */' }));
const page = await context.newPage();
const errors = [];
const failedRequests = [];
page.on('requestfailed', request => failedRequests.push({ url: request.url(), reason: request.failure()?.errorText }));
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const out = 'storage/app/cms-workspace-qa';
await mkdir(out, { recursive: true });
const results = [];
const waitReady = () => page.waitForFunction(() => document.querySelector('.admin-post-layout')?.dataset.analyzerMode && document.querySelector('[data-article-editor-field]')?.dataset.bound);
const metrics = () => page.evaluate(() => {
    const rect = selector => { const node = document.querySelector(selector); const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, bottom: r.bottom }; };
    return {
        sidebar: rect('.cms-sidebar'), editor: rect('.admin-post-main'), analyzer: rect('[data-seo-analysis]'), workspace: rect('.admin-post-layout'), actions: rect('.admin-post-actions'),
        state: document.documentElement.dataset.cmsSidebar, mode: document.querySelector('.admin-post-layout').dataset.analyzerMode,
        overflow: document.documentElement.scrollWidth > innerWidth, instances: document.querySelectorAll('[data-seo-analysis]').length,
    };
});
try {
    await page.goto('/admin/login');
    await page.locator('#email').fill(process.env.CMS_QA_EMAIL || 'workspace-qa@example.test');
    await page.locator('#password').fill(process.env.CMS_QA_PASSWORD || 'Workspace-QA-local-only');
    await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Masuk', exact: true }).click()]);
    for (const [width, height] of [[1920, 1080], [1440, 900], [1366, 768], [1280, 800], [1024, 768], [768, 1024], [390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => localStorage.removeItem('cms-sidebar-state'));
        await page.goto('/admin/posts/create');
        await waitReady();
        let m = await metrics();
        assert.equal(m.state, width < 1440 ? 'collapsed' : 'expanded');
        assert.equal(m.mode, width >= 1280 ? 'inline' : 'drawer');
        assert.equal(m.instances, 1);
        assert.equal(m.overflow, false, `page overflow at ${width}`);
        if (m.mode === 'inline') {
            assert.ok(m.editor.width >= 650);
            assert.equal(m.analyzer.width, 350);
            assert.ok(m.analyzer.x >= m.editor.x + m.editor.width + 23);
            assert.ok(m.analyzer.bottom < m.actions.y, 'analyzer clears the save bar');
            await page.evaluate(() => scrollTo(0, 900));
            assert.ok(await page.locator('[data-seo-analysis]').evaluate(node => node.getBoundingClientRect().y <= 17));
            await page.evaluate(() => scrollTo(0, 0));
        } else {
            await page.getByRole('button', { name: 'SEO & AEO', exact: true }).click();
            assert.equal(await page.locator('[data-seo-analysis]').evaluate(node => node.open), true);
            const drawer = await page.locator('[data-seo-analysis]').boundingBox();
            assert.ok(drawer.x >= 0 && drawer.x + drawer.width <= width);
            assert.equal(await page.locator('[data-analysis-close]').evaluate(node => node === document.activeElement), true);
            if (width < 1024) {
                for (let i = 0; i < 8; i++) await page.keyboard.press('Tab');
                assert.equal(await page.locator('[data-seo-analysis]').evaluate(node => node.contains(document.activeElement)), true);
            }
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('[data-seo-analysis]').evaluate(node => node.open), false);
            assert.equal(await page.locator('[data-analysis-open]').evaluate(node => node === document.activeElement), true);
            await page.locator('#title').fill(`Realtime ${width}`);
            await page.waitForFunction(title => document.querySelector('[data-analysis-preview-title]').textContent.includes(title), `Realtime ${width}`);
            await page.getByRole('button', { name: 'SEO & AEO', exact: true }).click();
            assert.ok((await page.locator('[data-analysis-preview-title]').textContent()).includes(`Realtime ${width}`));
            await page.screenshot({ path: `${out}/${width}-drawer.png` });
            await page.locator('[data-analysis-close]').click();
        }
        await page.screenshot({ path: `${out}/${width}-default.png` });
        if (width >= 1024) {
            await page.locator('[data-sidebar-toggle]').click();
            await page.waitForTimeout(250);
            m = await metrics();
            await page.screenshot({ path: `${out}/${width}-toggled.png` });
            assert.equal(m.sidebar.width, m.state === 'collapsed' ? 72 : 256);
            assert.equal(m.overflow, false);
            if (m.state === 'collapsed') {
                await page.locator('.cms-nav[aria-label="Articles"]').hover();
                await page.locator('[data-sidebar-tooltip]').waitFor({ state: 'visible' });
                assert.equal(await page.locator('[data-sidebar-tooltip]').textContent(), 'Articles');
            }
            if (width === 1280) assert.equal(m.mode, 'drawer');
            const state = m.state;
            await page.locator('.cms-nav[aria-label="Categories"]').click();
            await page.waitForURL('**/admin/categories');
            assert.equal(await page.evaluate(() => document.documentElement.dataset.cmsSidebar), state);
            await page.reload();
            assert.equal(await page.evaluate(() => document.documentElement.dataset.cmsSidebar), state);
            await page.goBack();
            await waitReady();
            assert.equal(await page.evaluate(() => document.documentElement.dataset.cmsSidebar), state);
            await page.goForward();
            await page.waitForURL('**/admin/categories');
            await page.goBack();
            await waitReady();
        } else {
            await page.locator('[data-sidebar-toggle]').click();
            assert.equal(await page.locator('.admin-main').evaluate(node => node.inert), true);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('.admin-main').evaluate(node => node.inert), false);
        }
        results.push({ width, height, defaultMode: width >= 1280 ? 'inline' : 'drawer', interaction: 'passed' });
        console.log(`PASS viewport ${width}x${height}`);
    }

    // Taxonomy/media dialogs remain in the native top layer above a laptop drawer.
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.evaluate(() => localStorage.setItem('cms-sidebar-state', 'expanded'));
    await page.goto('/admin/posts/create');
    await waitReady();
    await page.getByRole('button', { name: 'SEO & AEO', exact: true }).click();
    for (const kind of ['categories', 'tags', 'topics']) {
        // A nonmodal drawer leaves keyboard access to the article form intact.
        await page.locator(`[data-taxonomy-open="${kind}"]`).focus();
        await page.keyboard.press('Enter');
        assert.equal(await page.locator('[data-taxonomy-dialog]').evaluate(node => node.matches(':modal')), true);
        const label = `Workspace ${kind} ${Date.now()}`;
        await page.locator('[data-taxonomy-name]').fill(label);
        await page.locator('[data-taxonomy-save]').click();
        await page.locator('[data-taxonomy-dialog]').waitFor({ state: 'hidden' });
        assert.ok(await page.locator(`[data-taxonomy-select="${kind}"]`).evaluate((node, name) => [...node.selectedOptions].some(option => option.textContent.trim() === name), label));
    }
    await page.locator('[data-analysis-close]').click();
    for (const kind of ['categories', 'tags', 'topics']) {
        await page.locator(`[data-taxonomy-select="${kind}"]`).evaluate(node => node.tomselect.open());
        await page.locator(`[data-taxonomy-select="${kind}"]`).evaluate(node => node.tomselect.close());
    }
    await page.locator('[data-article-image-field="featured"] [data-image-choose]').click();
    assert.equal(await page.locator('[data-article-media-picker]').evaluate(node => node.matches(':modal')), true);
    await page.locator('[data-picker-tab="library"]').click();
    await page.locator('[data-picker-cancel]').click();
    await page.locator('[data-article-editor]').fill('Workspace browser QA content.');
    await page.locator('[data-editor-block]').selectOption('h2');
    assert.equal(await page.locator('[data-article-editor] h2').count(), 1);
    await page.locator('[data-editor-command="image"]').click();
    assert.equal(await page.locator('[data-editor-dialog="image"]').evaluate(node => node.matches(':modal')), true);
    await page.locator('[data-editor-dialog="image"] [data-editor-close]').click();
    await page.locator('[data-editor-command="table"]').click();
    assert.equal(await page.locator('[data-article-editor] table').count(), 1);
    await page.locator('[data-editor-command="code"]').click();
    await page.locator('[data-editor-code-input]').fill('echo "workspace QA";');
    await page.locator('[data-editor-code-insert]').click();
    assert.equal(await page.locator('[data-article-editor] pre code').count(), 1);
    await page.locator('#title').fill(`CMS workspace browser QA ${Date.now()}`);
    await page.getByRole('button', { name: 'SEO & AEO', exact: true }).click();
    await page.locator('.admin-post-actions button').click();
    await page.waitForURL('**/admin/posts/*/edit');
    await waitReady();
    assert.equal(await page.locator('[data-seo-analysis]').count(), 1);
    const updatedTitle = `Updated workspace QA ${Date.now()}`;
    await page.locator('#title').fill(updatedTitle);
    await Promise.all([page.waitForEvent('load'), page.locator('.admin-post-actions button').click()]);
    await waitReady();
    assert.equal(await page.locator('#title').inputValue(), updatedTitle);
    for (const [width, height] of [[1920, 1080], [1440, 900], [1366, 768], [1280, 800], [1024, 768], [768, 1024], [390, 844]]) {
        await page.setViewportSize({ width, height });
        await page.evaluate(() => localStorage.removeItem('cms-sidebar-state'));
        await page.reload();
        await waitReady();
        const m = await metrics();
        assert.equal(m.overflow, false);
        assert.equal(m.instances, 1);
        assert.equal(m.mode, width >= 1280 ? 'inline' : 'drawer');
        await page.screenshot({ path: `${out}/${width}-edit.png` });
    }
    await page.locator('.admin-post-actions a').filter({ hasText: 'Batal' }).click();
    await page.waitForURL('**/admin/posts');
    assert.deepEqual(errors, []);
    console.log('PASS realtime, navigation/reload/back, taxonomy/media dialogs, editor heading, create/update/cancel, no console errors');
    await writeFile(`${out}/results.json`, JSON.stringify({ results, errors, failedRequests, offlineFonts }, null, 2));
} catch (error) {
    console.error('QA failure at', page.url(), 'console errors:', errors, 'failed requests:', failedRequests);
    await page.screenshot({ path: `${out}/failure.png` });
    throw error;
} finally { await browser.close(); }
