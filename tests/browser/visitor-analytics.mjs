import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.ANALYTICS_QA_URL || 'http://127.0.0.1:8769';
if (!['localhost','127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('Local QA only.');
const out = 'storage/app/visitor-analytics-qa';
await mkdir(out, { recursive: true });
const browser = await chromium.launch({headless:true,executablePath:process.env.ANALYTICS_QA_CHROMIUM});
const errors = [];
const results = [];
const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36';
const context = await browser.newContext({baseURL, userAgent, viewport:{width:1440,height:1000}});
await context.route('https://fonts.googleapis.com/**',route=>route.fulfill({contentType:'text/css',body:''}));
const page = await context.newPage();
page.setDefaultTimeout(12000);
page.on('pageerror',error=>errors.push(error.message));
page.on('console',message=>{if(message.type()==='error')errors.push(message.text());});
const snapshot = () => JSON.parse(execFileSync(process.env.ANALYTICS_QA_PHP || 'php',['-d','xdebug.mode=off','tests/browser/seed-visitor-analytics.php','stats'],{env:process.env,encoding:'utf8'}));
const display = async path => {
    const confirmed = page.waitForResponse(response=>response.url().endsWith('/visits/confirm') && response.request().method()==='POST');
    await page.goto(path);
    assert.equal((await confirmed).status(),204);
};
async function login(role) {
    await page.goto('/admin/login');
    await page.locator('#email').fill(`${role}-analytics@example.test`);
    await page.locator('#password').fill('Analytics-QA-local-only');
    await page.getByRole('button',{name:'Masuk',exact:true}).click();
    await page.waitForURL('**/admin');
}
try {
    const initial = snapshot();
    await display('/');
    assert.equal(snapshot().views,initial.views+1);
    const cookie = (await context.cookies()).find(cookie=>cookie.name==='besofton_visitor');
    assert.ok(cookie?.httpOnly);
    assert.equal(cookie.sameSite,'Lax');
    await page.evaluate(()=>{document.dispatchEvent(new Event('livewire:navigated'));window.dispatchEvent(new Event('pageshow'));});
    await page.waitForTimeout(500);
    assert.equal(snapshot().views,initial.views+1);
    results.push({test:'visible guest homepage counted once; cookie HttpOnly/SameSite; duplicate lifecycle ignored',pass:true});

    const link = page.locator('.swiper-slide-active .featured-title a');
    await link.scrollIntoViewIfNeeded();
    const box = await link.boundingBox();
    const prefetch = page.waitForResponse(response=>response.url().endsWith('/analytics-browser-1') && response.request().method()==='GET');
    await page.mouse.move(box.x+10,box.y+10);
    await page.mouse.down();
    await prefetch;
    await page.mouse.move(0,0,{steps:10});
    await page.mouse.up();
    await page.waitForTimeout(500);
    assert.equal(snapshot().post.tracked_views,initial.post.tracked_views);
    await display('/analytics-browser-1');
    const viewed = snapshot();
    assert.equal(viewed.post.views,initial.post.views+1);
    assert.equal(viewed.post.legacy_views,80);
    assert.equal(viewed.unique,initial.unique+1);
    await display('/analytics-browser-1');
    assert.equal(snapshot().post.views,viewed.post.views);
    await page.waitForTimeout(10500);
    await display('/analytics-browser-1');
    assert.equal(snapshot().post.views,viewed.post.views+1);
    assert.equal(snapshot().unique,viewed.unique);
    results.push({test:'prefetch excluded; actual article display counts; fast refresh ignored; return visit counts without new unique',pass:true});

    await login('superadmin');
    await page.goto('/admin/analytics');
    await page.locator('[data-analytics-chart] .apexcharts-canvas').waitFor();
    await page.locator('[data-analytics-series]').selectOption('visitors');
    await page.waitForTimeout(350);
    await page.locator('[data-analytics-series]').selectOption('both');
    await page.locator('#analytics-period').selectOption('today');
    await page.getByRole('button',{name:'Terapkan periode'}).click();
    await page.waitForURL('**/admin/analytics?**');
    await page.locator('[data-analytics-chart] .apexcharts-canvas').waitFor();
    await page.locator('#analytics-q').fill('tidak-cocok');
    await page.getByRole('button',{name:'Filter tabel artikel'}).click();
    await page.getByText('Tidak ada artikel yang cocok.').waitFor();
    await page.locator('#analytics-q').fill('Artikel Analytics 1');
    await page.locator('#analytics-sort').selectOption('period_unique');
    await page.locator('#analytics-category').selectOption({label:'Analytics QA'});
    await page.getByRole('button',{name:'Filter tabel artikel'}).click();
    await page.locator('tbody strong').filter({hasText:'Artikel Analytics 1'}).waitFor();
    const csvResponse = await context.request.get('/admin/analytics/export?period=today');
    assert.equal(csvResponse.status(),200);
    const csv = await csvResponse.text();
    assert.ok(csv.includes('Besofton Insights'));
    assert.ok(csv.includes(`views;${snapshot().views}`));
    assert.equal((await context.request.get('/admin/analytics/print?period=today')).status(),200);
    for(const width of [1440,390]) {
        await page.setViewportSize({width,height:1000});
        await page.waitForFunction(()=>document.documentElement.scrollWidth<=innerWidth);
        assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
        await page.screenshot({path:`${out}/dashboard-${width}.png`,fullPage:true});
    }
    results.push({test:'Superadmin chart, period, article search/category/sort, CSV/print, responsive 1440/390',pass:true});

    await page.goto('/admin/posts');
    assert.ok(await page.getByRole('columnheader',{name:'Views',exact:true}).isVisible());
    await page.locator('a').filter({hasText:'Artikel Analytics 1'}).first().click();
    await page.locator('[data-article-statistics] .apexcharts-canvas').waitFor();
    const title = await page.locator('#title').inputValue();
    await page.getByRole('button',{name:'Simpan perubahan',exact:true}).click();
    await page.waitForTimeout(600);
    assert.equal(await page.locator('#title').inputValue(),title);
    assert.equal(await page.locator('[data-post-form] [data-article-statistics]').count(),0);
    const beforeStaffPublic = snapshot().views;
    await page.goto('/');
    assert.equal(await page.locator('[data-visit-receipt]').count(),0);
    assert.equal(snapshot().views,beforeStaffPublic);
    results.push({test:'Superadmin Views column and read-only editor panel; editor save and SEO form stay independent; staff public visit excluded',pass:true});

    const logout = await context.request.post('/admin/logout',{form:{_token:await page.locator('input[name="_token"]').first().getAttribute('value')}});
    assert.ok([200,302,419].includes(logout.status()));
    await context.clearCookies();
    await login('editor');
    assert.equal(await page.getByText('Visitor Analytics',{exact:true}).count(),0);
    assert.equal((await context.request.get('/admin/analytics')).status(),403);
    assert.equal((await context.request.get('/admin/analytics/export')).status(),403);
    assert.equal((await context.request.get('/admin/analytics/print')).status(),403);
    await page.goto('/admin/posts');
    assert.equal(await page.getByRole('columnheader',{name:'Views',exact:true}).count(),0);
    await page.locator('a').filter({hasText:'Artikel Analytics 1'}).first().click();
    assert.equal(await page.locator('[data-article-statistics]').count(),0);
    assert.equal(await page.locator('[data-analytics-chart]').count(),0);
    results.push({test:'Editor with all permissions denied dashboard/export/print; no menu, Views column, panel, chart or hydration stats',pass:true});
    assert.deepEqual(errors,[]);
} finally {
    await writeFile(`${out}/browser-results.json`,JSON.stringify({results,errors},null,2));
    console.log(JSON.stringify({results,errors},null,2));
    await browser.close();
}
