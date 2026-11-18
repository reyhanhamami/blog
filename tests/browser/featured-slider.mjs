import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.SLIDER_QA_URL || 'http://127.0.0.1:8768';
if (!['127.0.0.1', 'localhost'].includes(new URL(baseURL).hostname)) throw new Error('Local QA only.');
const out = 'storage/app/featured-slider-qa';
await mkdir(out, { recursive: true });
const browser = await chromium.launch({ headless: true, executablePath: process.env.SLIDER_QA_CHROMIUM });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 } });
await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
const page = await context.newPage();
page.setDefaultTimeout(10000);
page.setDefaultNavigationTimeout(15000);
const errors = [];
context.on('page', tab => {
    tab.on('pageerror', error => errors.push(error.message));
    tab.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
});
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
const results = [];
const seed = count => execFileSync(process.env.SLIDER_QA_PHP || 'php', ['-d', 'xdebug.mode=off', 'tests/browser/seed-featured-slider.php', String(count)], { env: process.env });
const ready = () => page.waitForFunction(() => !!document.querySelector('[data-featured-swiper]')?.swiper);
const active = () => page.evaluate(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex);
const settled = index => page.waitForFunction(i => {
    const swiper = document.querySelector('[data-featured-swiper]')?.swiper;
    return swiper?.activeIndex === i && !swiper.animating;
}, index);
const leave = async () => {
    await page.mouse.move(0, 0);
    await page.locator('.hero-search input').focus();
};
const home = async () => { await page.goto('/'); await ready(); };
const navigateAndBack = async (selector, slug) => {
    await page.locator(selector).click();
    await page.waitForURL(`**/${slug}`);
    await page.goBack();
    await ready();
};
try {
    seed(1);
    await page.goto('/');
    assert.equal(await page.locator('.swiper-slide').count(), 1);
    assert.equal(await page.locator('[data-featured-toggle]').count(), 0);
    assert.equal(await page.evaluate(() => !!document.querySelector('[data-featured-swiper]').swiper), false);
    assert.equal(await page.locator('script[src*="public-home-"]').count(), 0);
    await page.locator('.featured-title a').click();
    await page.waitForURL('**/slider-qa-1');
    results.push({ test: 'one article: no Swiper/autoplay bundle, title click works', pass: true });
    console.log('PASS one article');

    // Start on an article: the conditional Vite entry must also work on first SPA entry.
    seed(2);
    await page.reload();
    console.log('Entering homepage from article', page.url());
    await page.locator('.public-breadcrumb a').first().click();
    await page.waitForURL(`${baseURL}/`);
    await ready();
    assert.equal(await page.locator('.swiper-slide').count(), 2);
    await page.locator('[data-featured-prev]').click();
    await settled(1); // rewind from first to last with only two real slides
    await navigateAndBack('.swiper-slide-active .public-gold-button', 'slider-qa-2');
    results.push({ test: 'two slides: first SPA entry, rewind Previous, second CTA', pass: true });
    console.log('PASS two articles');

    seed(3);
    for (const width of [1440, 1024, 768, 390]) {
        await page.setViewportSize({ width, height: 1000 });
        for (const target of ['.featured-title a', '.public-gold-button']) {
            for (let index = 0; index < 3; index++) {
                await home();
                for (let step = 0; step < index; step++) {
                    await page.locator('[data-featured-next]').click();
                    await settled(step + 1);
                }
                assert.equal(await page.locator(`.swiper-slide-active ${target}`).getAttribute('href'), `${baseURL}/slider-qa-${index + 1}`);
                await navigateAndBack(`.swiper-slide-active ${target}`, `slider-qa-${index + 1}`);
            }
        }
        await home();
        await page.locator('.home-featured').scrollIntoViewIfNeeded();
        await page.mouse.move(0, 0);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `overflow ${width}`);
        const layout = await page.locator('.featured-article').first().evaluate(node => {
            const media = node.querySelector('.featured-visual').getBoundingClientRect();
            const copy = node.querySelector('.featured-copy').getBoundingClientRect();
            return { media: { x: media.x, y: media.y, width: media.width }, copy: { x: copy.x, y: copy.y, width: copy.width } };
        });
        assert.ok(width > 767 ? layout.media.x < layout.copy.x : layout.media.y < layout.copy.y);
        const spacing = await page.locator('.home-featured').evaluate(region => {
            const slide = region.querySelector('.swiper-slide-active');
            return {
                controlsTop: region.querySelector('.featured-controls').getBoundingClientRect().top,
                metaBottom: slide.querySelector('.public-post-meta').getBoundingClientRect().bottom,
                ctaBottom: slide.querySelector('.public-gold-button').getBoundingClientRect().bottom,
            };
        });
        assert.ok(spacing.controlsTop >= spacing.metaBottom + 8, `controls overlap metadata ${width}`);
        assert.ok(spacing.controlsTop >= spacing.ctaBottom + 8, `controls overlap CTA ${width}`);
        await page.locator('.home-featured').screenshot({ path: `${out}/slider-${width}.png` });
        results.push({ test: 'all three titles and CTAs, Next and Back, no overflow, layout', width, pass: true, layout });
        console.log('PASS all anchors viewport', width);
    }

    await page.setViewportSize({ width: 1440, height: 1000 });
    await home();
    await leave();
    const started = Date.now();
    await page.waitForFunction(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex === 1, { timeout: 8000 });
    const elapsed = Date.now() - started;
    assert.ok(elapsed >= 4500 && elapsed < 7500, `autoplay interval ${elapsed}`);
    await settled(1);
    await navigateAndBack('.swiper-slide-active .featured-title a', 'slider-qa-2');
    results.push({ test: 'real 5-second autoplay then title click', elapsed, pass: true });

    await home();
    await page.locator('[data-featured-prev]').click();
    await settled(2);
    await leave();
    await page.waitForFunction(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex === 0, { timeout: 8000 });
    await settled(0);
    await navigateAndBack('.swiper-slide-active .public-gold-button', 'slider-qa-1');
    results.push({ test: 'last-to-first autoplay and CTA click', pass: true });

    await home();
    await page.locator('.swiper-slide-active .featured-title a').hover();
    const hovered = await active();
    await page.waitForTimeout(5400);
    assert.equal(await active(), hovered);
    await leave();
    await page.waitForFunction(() => document.querySelector('[data-featured-swiper]').swiper.activeIndex !== 0, { timeout: 8000 });
    await page.locator('.swiper-slide-active .featured-title a').focus();
    const focused = await active();
    await page.waitForTimeout(5400);
    assert.equal(await active(), focused);
    await page.keyboard.press('Enter');
    await page.waitForURL(`**/slider-qa-${focused + 1}`);
    await page.goBack();
    await ready();
    await page.locator('[data-featured-toggle]').click();
    await leave();
    const paused = await active();
    await page.waitForTimeout(5400);
    assert.equal(await active(), paused);
    await page.locator('[data-featured-toggle]').click();
    await leave();
    await page.waitForFunction(i => document.querySelector('[data-featured-swiper]').swiper.activeIndex !== i, paused, { timeout: 8000 });
    results.push({ test: 'hover, focus, keyboard Enter, explicit pause/resume', pass: true });

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await home();
    assert.equal(await page.locator('[data-featured-toggle]').isVisible(), false);
    await leave();
    await page.waitForTimeout(5400);
    assert.equal(await active(), 0);
    await page.locator('[data-featured-next]').click();
    await settled(1);
    await navigateAndBack('.swiper-slide-active .featured-title a', 'slider-qa-2');
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await leave();
    results.push({ test: 'reduced motion disables autoplay, manual navigation still works', pass: true });

    // A drag beginning on a real link must not trigger Livewire's mouseup navigation.
    await home();
    const link = await page.locator('.swiper-slide-active .featured-title a').boundingBox();
    await page.mouse.move(link.x + link.width * .8, link.y + 10);
    await page.mouse.down();
    await page.mouse.move(link.x + 5, link.y + 10, { steps: 15 });
    await page.mouse.up();
    await page.waitForTimeout(800);
    assert.equal(page.url(), `${baseURL}/`);
    assert.ok(await active() > 0);
    results.push({ test: 'mouse drag starting on anchor does not navigate', pass: true });

    await home();
    for (const button of ['left', 'middle']) {
        const popup = context.waitForEvent('page');
        await page.locator('.swiper-slide-active .featured-title a').click(button === 'left' ? { modifiers: ['Control'] } : { button });
        const opened = await popup;
        await opened.waitForURL('**/slider-qa-1');
        await opened.close();
        assert.equal(page.url(), `${baseURL}/`);
    }
    results.push({ test: 'Ctrl+Click and middle click open correct native anchor in new tab', pass: true });

    // Store the actual instance and ensure repeated lifecycle notifications reuse it.
    await page.evaluate(() => { window.initialSlider = document.querySelector('[data-featured-swiper]').swiper; document.dispatchEvent(new Event('livewire:navigated')); document.dispatchEvent(new Event('livewire:navigated')); });
    assert.equal(await page.evaluate(() => window.initialSlider === document.querySelector('[data-featured-swiper]').swiper), true);
    for (let index = 0; index < 3; index++) {
        await navigateAndBack('.swiper-slide-active .public-gold-button', 'slider-qa-1');
        await page.goForward();
        await page.waitForURL('**/slider-qa-1');
        await page.goBack();
        await ready();
    }
    assert.equal(await page.evaluate(() => window.initialSlider.destroyed), true);
    results.push({ test: 'one instance; old instance destroyed; repeated SPA and Back/Forward', pass: true });
    assert.deepEqual(errors, []);
    results.push({ test: 'no JavaScript console/page errors', pass: true });
} finally {
    await writeFile(`${out}/results.json`, JSON.stringify({ results, errors }, null, 2));
    console.log(JSON.stringify({ results, errors }, null, 2));
    await browser.close();
}
