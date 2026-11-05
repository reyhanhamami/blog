import assert from 'node:assert/strict';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.THEME_QA_URL || 'http://127.0.0.1:8767';
if (!['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) throw new Error('Isolated local QA only.');
const out = 'storage/app/public-presentation-qa';
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.THEME_QA_CHROMIUM });
const context = await browser.newContext({ baseURL });
await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
const page = await context.newPage();
const errors = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const results = [];
const widths = [1920, 1440, 1366, 1024, 768, 390];
const productionImage = await readFile(`${out}/production-featured.webp`);
const originalHash = createHash('sha256').update(productionImage).digest('hex');
const wideImage = await readFile('public/uploads/qa-presentation-wide.png');
const frames = () => page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
const noOverflow = async label => assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `${label}: horizontal overflow`);
const readySlider = () => page.waitForFunction(() => !!document.querySelector('[data-featured-swiper]')?.swiper);
const imageMetrics = () => page.locator('.swiper-slide-active .featured-image').evaluate(image => {
    const wrapper = image.closest('.featured-visual');
    const rect = node => { const r = node.getBoundingClientRect(); return { x: r.x, y: r.y, width: r.width, height: r.height, bottom: r.bottom, right: r.right }; };
    return { image: rect(image), wrapper: rect(wrapper), natural: [image.naturalWidth, image.naturalHeight], fit: getComputedStyle(image).objectFit, background: getComputedStyle(wrapper).backgroundColor, fill: !!wrapper.querySelector('[aria-hidden="true"].featured-image-fill') };
});
const verifyImage = async (width, label) => {
    const image = page.locator('.swiper-slide-active .featured-image');
    await image.scrollIntoViewIfNeeded();
    await image.evaluate(node => node.decode());
    await frames();
    const m = await imageMetrics();
    assert.equal(m.fit, 'contain');
    assert.ok(Math.abs(m.image.width / m.image.height - m.natural[0] / m.natural[1]) < .005, `${label}: intrinsic ratio`);
    assert.ok(m.image.y >= m.wrapper.y - 1 && m.image.bottom <= m.wrapper.bottom + 1, `${label}: uncropped vertically`);
    assert.ok(m.image.x >= m.wrapper.x - 1 && m.image.right <= m.wrapper.right + 1, `${label}: uncropped horizontally`);
    assert.notEqual(m.background, 'rgb(24, 23, 21)', `${label}: original black layout removed`);
    assert.equal(m.fill, true);
    await noOverflow(label);
    if ([1440, 390].includes(width)) await page.locator('.home-featured').screenshot({ path: `${out}/featured-${label}-${width}.png` });
    results.push({ kind: 'image', width, label, ...m });
};
const sidebarMetrics = () => page.evaluate(() => {
    const rect = node => { const r = node.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, height: r.height }; };
    const inner = document.querySelector('.article-sidebar-inner');
    const sidebar = document.querySelector('.article-sidebar');
    const layout = document.querySelector('.article-layout');
    const header = document.querySelector('.public-site-header');
    return { inner: rect(inner), sidebar: rect(sidebar), layout: rect(layout), header: rect(header), position: getComputedStyle(inner).position, scroll: inner.scrollTop, scrollHeight: inner.scrollHeight, clientHeight: inner.clientHeight, pageScroll: scrollY, visible: sidebar.offsetParent !== null };
});
try {
    await page.goto('/admin/login');
    await page.locator('#email').fill('theme-qa@example.test');
    await page.locator('#password').fill('Theme-QA-local-only');
    await Promise.all([page.waitForURL('**/admin'), page.getByRole('button', { name: 'Masuk', exact: true }).click()]);
    await page.goto('/admin/settings');
    await page.locator('#public-theme-preset').selectOption('Red White');
    await page.getByRole('button', { name: 'Simpan Tema', exact: true }).click();
    await page.getByText('Pengaturan tampilan website berhasil diperbarui.', { exact: true }).waitFor();

    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        await page.goto('/');
        await readySlider();
        for (let index = 0; index < 3; index++) {
            await page.waitForFunction(i => { const swiper = document.querySelector('[data-featured-swiper]').swiper; return swiper.activeIndex === i && !swiper.animating; }, index);
            await verifyImage(width, ['landscape', 'square', 'portrait'][index]);
            if (index < 2) await page.locator('[data-featured-next]').click();
        }
        for (const [label, body, contentType] of [['wide', wideImage, 'image/png'], ['production-original', productionImage, 'image/webp']]) {
            await page.route('**/uploads/qa-presentation-landscape.png', route => route.fulfill({ contentType, body }));
            await page.reload();
            await readySlider();
            await verifyImage(width, label);
            await page.unroute('**/uploads/qa-presentation-landscape.png');
        }
    }
    console.log('Featured image QA: 30 image/viewport combinations passed.');

    await page.setViewportSize({ width: 1440, height: 900 });
    let releaseImages;
    const heldImages = new Promise(resolve => { releaseImages = resolve; });
    await page.route('**/uploads/qa-presentation-*.png', async route => { await heldImages; await route.continue(); });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    await readySlider();
    await page.locator('.home-featured').scrollIntoViewIfNeeded();
    const beforeLoad = await page.locator('.home-featured').boundingBox();
    releaseImages();
    await page.locator('.swiper-slide-active .featured-image').evaluate(node => node.decode());
    await frames();
    const afterLoad = await page.locator('.home-featured').boundingBox();
    assert.ok(Math.abs(beforeLoad.height - afterLoad.height) < 1, 'featured height stable while images load');
    await page.unroute('**/uploads/qa-presentation-*.png');

    for (const width of widths) {
        await page.setViewportSize({ width, height: 900 });
        for (const kind of ['short', 'long']) {
            await page.goto(`/presentation-sidebar-${kind}`);
            await page.locator('#article-toc-desktop ol').waitFor({ state: 'attached' });
            await page.waitForFunction(() => parseFloat(document.body.style.getPropertyValue('--public-header-height')) === Math.ceil(document.querySelector('.public-site-header').getBoundingClientRect().height));
            await noOverflow(`sidebar ${kind} ${width}`);
            if (width <= 900) {
                assert.equal((await sidebarMetrics()).visible, false);
                assert.equal(await page.locator('#article-toc').evaluate(node => getComputedStyle(node).position), 'static');
                await page.locator('#article-toc a').first().click();
                assert.ok(await page.locator('.article-content h2').first().evaluate(node => node.getBoundingClientRect().top >= document.querySelector('.public-site-header').getBoundingClientRect().bottom - 1));
            } else {
                const start = await page.locator('.article-layout').evaluate(node => node.getBoundingClientRect().top + scrollY);
                await page.evaluate(y => scrollTo(0, y + 600), start);
                await frames();
                const first = await sidebarMetrics();
                assert.equal(first.position, 'sticky');
                assert.ok(Math.abs(first.inner.top - first.header.bottom - 24) <= 2, 'sticky below actual header');
                await page.evaluate(y => scrollTo(0, y + 1400), start);
                await frames();
                const second = await sidebarMetrics();
                assert.ok(Math.abs(first.inner.top - second.inner.top) <= 1, 'sidebar follows page scroll');
                assert.ok(second.inner.bottom <= 900 - 23, 'sticky sidebar fits viewport');
                if (kind === 'long') {
                    assert.ok(second.scrollHeight > second.clientHeight, 'long TOC scrolls internally');
                    await page.locator('.article-sidebar-inner').evaluate(node => { node.scrollTop = 120; });
                    assert.ok((await sidebarMetrics()).scroll > 0);
                    assert.equal((await sidebarMetrics()).pageScroll, second.pageScroll);
                }
                await page.locator('#article-toc-desktop a').first().click();
                assert.ok(await page.locator('.article-content h2').first().evaluate(node => node.getBoundingClientRect().top >= document.querySelector('.public-site-header').getBoundingClientRect().bottom - 1), 'TOC heading clears header');
                if (width === 1440) await page.screenshot({ path: `${out}/sidebar-${kind}-${width}.png` });
                const end = await page.locator('.article-layout').evaluate(node => node.getBoundingClientRect().bottom + scrollY);
                await page.evaluate(y => scrollTo(0, y - 250), end);
                await frames();
                const bottom = await sidebarMetrics();
                assert.ok(bottom.inner.bottom <= bottom.layout.bottom + 1, 'sticky stays inside article layout');
                const footerTop = await page.locator('.public-footer-newsletter').evaluate(node => node.getBoundingClientRect().top);
                assert.ok(bottom.inner.bottom < footerTop, 'no footer overlap');
            }
            if (width === 390) await page.screenshot({ path: `${out}/sidebar-${kind}-${width}.png` });
            results.push({ kind: 'sidebar', width, toc: kind, passed: true });
        }
    }
    await page.setViewportSize({ width: 1440, height: 480 });
    await page.goto('/presentation-sidebar-long');
    await page.locator('#article-toc-desktop ol').waitFor();
    await page.locator('.public-header-inner').evaluate(node => { node.style.minHeight = '132px'; });
    await page.waitForFunction(() => parseFloat(document.body.style.getPropertyValue('--public-header-height')) === 133);
    const start = await page.locator('.article-layout').evaluate(node => node.getBoundingClientRect().top + scrollY);
    await page.evaluate(y => scrollTo(0, y + 600), start);
    await frames();
    const resized = await sidebarMetrics();
    assert.ok(Math.abs(resized.inner.top - resized.header.bottom - 24) <= 2);
    assert.ok(resized.inner.bottom <= 480 - 23, 'tall header and short viewport stay readable');
    assert.deepEqual(errors, [], 'no browser/console errors');
    assert.equal(createHash('sha256').update(await readFile(`${out}/production-featured.webp`)).digest('hex'), originalHash, 'original source untouched');
    await writeFile(`${out}/results.json`, JSON.stringify({ checks: results.length, results, errors, imageLoadHeightShift: afterLoad.height - beforeLoad.height, productionImage: { width: 1200, height: 630, sha256: originalHash }, resizedHeader: resized, fonts: 'Offline fallback' }, null, 2));
    console.log(`Presentation QA passed: ${results.length} viewport cases, delayed image load, header resizing, and footer bounds.`);
} catch (error) {
    await page.screenshot({ path: `${out}/failure.png` }).catch(() => {});
    await writeFile(`${out}/failure.json`, JSON.stringify({ error: error.message, errors, completed: results.length }, null, 2));
    throw error;
} finally {
    await browser.close();
}
