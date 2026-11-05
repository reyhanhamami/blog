// Uses an already-installed playwright-core and Chromium; no application dependency.
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.THEME_QA_URL || 'http://127.0.0.1:8767';
if (!['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('Local isolated QA server only.');
const browser = await chromium.launch({ headless: true, executablePath: process.env.THEME_QA_CHROMIUM });
const context = await browser.newContext({ baseURL });
await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
await context.route('https://i.ytimg.com/**', route => route.fulfill({ contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jGZkAAAAASUVORK5CYII=', 'base64') }));
const page = await context.newPage();
const publicPage = await context.newPage();
const errors = [];
for (const tab of [page, publicPage]) {
    tab.on('pageerror', error => errors.push(error.message));
    tab.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
}
const out = 'storage/app/public-theme-qa';
await mkdir(out, { recursive: true });
const results = [];
const style = (tab, selector, property) => tab.locator(selector).first().evaluate((node, prop) => getComputedStyle(node).getPropertyValue(prop).trim(), property);
const ready = async () => {
    await page.locator('#theme-primary').waitFor();
    await page.waitForFunction(() => document.querySelector('[data-theme-preview]')?.style.getPropertyValue('--public-primary'));
};
const save = async () => {
    const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().includes('/livewire'));
    await page.getByRole('button', { name: 'Simpan Tema', exact: true }).click();
    assert.equal((await response).status(), 200);
    await page.getByText('Pengaturan tampilan website berhasil diperbarui.', { exact: true }).waitFor();
    await page.waitForFunction(() => document.querySelector('[data-theme-form]')?.dataset.dirty === '0');
};
try {
    await page.goto('/admin/login');
    await page.locator('#email').fill('theme-qa@example.test');
    await page.locator('#password').fill('Theme-QA-local-only');
    await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Masuk', exact: true }).click()]);
    await page.goto('/admin/settings');
    await ready();
    const adminBefore = await style(page, 'body', 'background-color');
    await page.locator('#theme-primary').fill('#2563eb');
    assert.ok(await page.locator('input[type=color]').count() > 0);
    await page.waitForFunction(() => document.querySelector('[data-theme-preview]').style.getPropertyValue('--public-primary') === '#2563eb');
    assert.equal(await page.locator('input[aria-label="Pilih Primary Color"]').inputValue(), '#2563eb');
    await publicPage.goto('/');
    assert.equal(await style(publicPage, 'body', '--public-primary'), '#e8af4b', 'unsaved preview remains local');
    await page.getByRole('button', { name: 'Reset Primary Color', exact: true }).click();
    assert.equal(await page.locator('#theme-primary').inputValue(), '#e8af4b');
    await page.locator('#theme-primary').fill('#12345g');
    assert.equal(await page.getByRole('button', { name: 'Simpan Tema', exact: true }).isDisabled(), true);
    await page.getByRole('button', { name: 'Reset Primary Color', exact: true }).click();
    await page.locator('#theme-text').fill('#ffffff');
    await page.locator('#theme-background').fill('#ffffff');
    assert.equal(await page.getByRole('button', { name: 'Simpan Tema', exact: true }).isDisabled(), true);
    await page.getByRole('button', { name: 'Batal', exact: true }).click();
    assert.equal(await page.locator('#theme-text').inputValue(), '#0a0a0a');

    const widths = [1920, 1440, 1366, 1024, 768, 390];
    const urls = ['/', '/theme-qa-article', '/kategori/theme-qa', '/cari?q=Digital', '/video/theme-qa-video', '/belajar/theme-qa-path', '/kelas/theme-qa-course', '/kelas/theme-qa-course/pelajaran/1', '/kuis/theme-qa-quiz', '/akun', '/about'];
    for (const preset of ['Besofton Classic', 'Modern Blue', 'Forest Green', 'Red White']) {
        await page.locator('#public-theme-preset').selectOption(preset);
        const expected = await page.locator('#theme-primary').inputValue();
        const background = await page.locator('#theme-background').inputValue();
        const previewBackground = await style(page, '[data-theme-preview]', 'background-color');
        assert.equal(previewBackground, `rgb(${[1, 3, 5].map(i => parseInt(background.slice(i, i + 2), 16)).join(', ')})`, 'preview background reflects current form');
        await save();
        console.log(`Checking ${preset}...`);
        assert.equal(await style(page, 'body', 'background-color'), adminBefore, 'CMS colors isolated');
        for (const width of widths) {
            await page.setViewportSize({ width, height: 900 });
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `admin overflow ${width}`);
            await publicPage.setViewportSize({ width, height: 900 });
            for (const url of urls) {
                const response = await publicPage.goto(url);
                assert.equal(response.status(), 200, `${url} status`);
                assert.equal(await style(publicPage, 'body', '--public-primary'), expected, `${url} runtime theme`);
                assert.equal(await publicPage.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${url} overflow ${width}`);
                const unreadable = await publicPage.locator('.public-button, .public-gold-button, .newsletter-form button, .public-login-link').evaluateAll(nodes => nodes.filter(node => {
                    const s = getComputedStyle(node);
                    return node.getBoundingClientRect().width > 0 && s.color === s.backgroundColor;
                }).map(node => node.className));
                assert.deepEqual(unreadable, [], `${url} invisible buttons`);
                results.push({ preset, width, url, status: 'passed' });
                if (url === '/' && [1440, 390].includes(width)) await publicPage.screenshot({ path: `${out}/${preset.replaceAll(' ', '-')}-${width}.png`, fullPage: true });
            }
        }
    }
    const finalPrimary = await page.locator('#theme-primary').inputValue();
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('#public-theme').evaluate(node => node.scrollIntoView({ block: 'start' }));
    await page.screenshot({ path: `${out}/editor-1440.png` });
    await page.getByRole('button', { name: 'Kembalikan ke Tema Default', exact: true }).click();
    await page.locator('.swal2-cancel').click();
    assert.equal(await page.locator('#theme-primary').inputValue(), finalPrimary);
    await page.getByRole('button', { name: 'Kembalikan ke Tema Default', exact: true }).click();
    await page.getByRole('button', { name: 'Reset warna', exact: true }).click();
    assert.equal(await page.locator('#theme-primary').inputValue(), '#e8af4b');
    await publicPage.goto('/');
    assert.equal(await style(publicPage, 'body', '--public-primary'), finalPrimary, 'reset is not active until Save');
    await save();
    assert.deepEqual(errors, [], 'no browser JS errors');
    await writeFile(`${out}/results.json`, JSON.stringify({ checks: results.length, results, errors, fonts: 'Google Fonts blocked for offline repeatability' }, null, 2));
    console.log(`Theme QA passed: ${results.length} route/viewport/palette combinations; editor save, reset, validation, preview and CMS isolation verified.`);
} catch (error) {
    await page.screenshot({ path: `${out}/editor-failure.png`, fullPage: true }).catch(() => {});
    await writeFile(`${out}/failure.json`, JSON.stringify({ error: error.message, errors, completed: results.length }, null, 2));
    console.error(JSON.stringify({ errors, completed: results.length }));
    throw error;
} finally {
    await browser.close();
}
