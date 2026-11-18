import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.SLIDER_QA_URL || 'http://127.0.0.1:8768';
if (!['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) throw new Error('Local QA only.');
const browser = await chromium.launch({ headless: true, executablePath: process.env.SLIDER_QA_CHROMIUM });
const results = [];
const errors = [];
try {
    const context = await browser.newContext({ baseURL, viewport: { width: 390, height: 1000 }, hasTouch: true, isMobile: true });
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    page.on('pageerror', error => errors.push(error.message));
    const home = async () => {
        await page.goto('/');
        await page.waitForFunction(() => document.querySelector('[data-featured-swiper]')?.swiper);
    };
    await home();
    await page.locator('.swiper-slide-active .featured-visual').scrollIntoViewIfNeeded();
    const box = await page.locator('.swiper-slide-active .featured-visual').boundingBox();
    const cdp = await context.newCDPSession(page);
    const y = box.y + box.height / 2;
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: box.x + box.width * .85, y }] });
    for (let step = 1; step <= 12; step++) {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: box.x + box.width * (.85 - step * .06), y }] });
    }
    await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
    await page.waitForFunction(() => {
        const s = document.querySelector('[data-featured-swiper]').swiper;
        return s.activeIndex === 1 && !s.animating;
    });
    assert.equal(page.url(), `${baseURL}/`);
    await page.locator('.swiper-slide-active .public-gold-button').tap();
    await page.waitForURL('**/slider-qa-2');
    results.push({ test: 'native CDP touch swipe at 390px, no accidental navigation, tap second CTA', pass: true });

    await home();
    await page.locator('.hero-search input').focus();
    const other = await context.newPage();
    await other.goto('about:blank');
    await other.bringToFront();
    const nativeHidden = await page.evaluate(() => document.hidden);
    if (!nativeHidden) {
        // Headless platforms may keep every tab visible. Exercise the real handler
        // with a controlled visibility state, and report this limitation explicitly.
        await page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
            Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => 'hidden' });
            document.dispatchEvent(new Event('visibilitychange'));
        });
    }
    const index = await page.evaluate(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex);
    await page.waitForTimeout(5400);
    assert.equal(await page.evaluate(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex), index);
    await other.close();
    await page.bringToFront();
    if (!nativeHidden) await page.evaluate(() => { delete document.hidden; delete document.visibilityState; document.dispatchEvent(new Event('visibilitychange')); });
    await page.waitForFunction(i => document.querySelector('[data-featured-swiper]').swiper.activeIndex !== i, index);
    results.push({ test: 'hidden tab stops autoplay and visible resumes', nativeVisibility: nativeHidden, pass: true });

    await page.locator('[data-featured-next]').tap();
    await page.locator('.hero-search input').focus();
    const afterManual = await page.evaluate(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex);
    await page.waitForFunction(i => document.querySelector('[data-featured-swiper]').swiper.activeIndex !== i, afterManual);
    results.push({ test: 'autoplay resumes after manual Next and focus leaves region', pass: true });
    assert.deepEqual(errors, []);
} finally {
    await mkdir('storage/app/featured-slider-qa', { recursive: true });
    await writeFile('storage/app/featured-slider-qa/touch-results.json', JSON.stringify({ results, errors }, null, 2));
    console.log(JSON.stringify({ results, errors }, null, 2));
    await browser.close();
}
