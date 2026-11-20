import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const baseURL = process.env.ARTICLE_SAVE_QA_URL || 'http://127.0.0.1:8770';
if (!['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('Local QA only.');
const browser = await chromium.launch({ headless: true, executablePath: process.env.ARTICLE_SAVE_QA_CHROMIUM });
const context = await browser.newContext({ baseURL, permissions: ['clipboard-read', 'clipboard-write'], viewport: { width: 1440, height: 1000 } });
await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
const page = await context.newPage();
page.setDefaultTimeout(15000);
const errors = [];
const saves = [];
const results = [];
const actionPattern = /\/admin\/posts(?:\/[0-9]+)?$/;
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => {
    // Deliberate transport failure tests generate Chrome resource-error messages.
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) errors.push(message.text());
});
page.on('request', request => {
    if (request.method() === 'POST' && actionPattern.test(new URL(request.url()).pathname)) {
        saves.push({ path: new URL(request.url()).pathname, method: request.method(), patch: /name="_method"\r\n\r\nPATCH/.test(request.postData() || '') });
    }
});
const ready = () => page.waitForFunction(() => document.querySelector('[data-article-editor-field]')?.dataset.bound && document.querySelector('[data-post-form]') && document.querySelector('.admin-post-layout')?.dataset.analyzerMode);
async function paste({ html, text }) {
    const editor = page.locator('[data-article-editor]');
    await editor.click();
    await page.keyboard.press('Control+a');
    await page.evaluate(async ({html,text}) => {
        const types = { 'text/plain': new Blob([text || 'Clipboard QA'], {type:'text/plain'}) };
        if (html) types['text/html'] = new Blob([html], {type:'text/html'});
        await navigator.clipboard.write([new ClipboardItem(types)]);
    }, {html,text});
    await page.keyboard.press('Control+v');
    await page.waitForTimeout(250);
}
async function save() {
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && actionPattern.test(new URL(response.url()).pathname));
    const navigated = page.waitForEvent('framenavigated', {predicate: frame => frame === page.mainFrame() && /\/admin\/posts\/[0-9]+\/edit$/.test(frame.url())});
    await page.locator('.admin-post-actions button').click();
    const response = await responsePromise;
    assert.equal(response.status(), 200);
    assert.ok(response.headers()['content-type'].includes('application/json'));
    await navigated;
    const redirect = page.url();
    await page.waitForLoadState('domcontentloaded');
    await ready();
    assert.equal(await page.locator('[data-post-save-error]:visible').count(), 0);
    assert.equal(await page.locator('[data-seo-analysis]').count(), 1);
    return redirect;
}
const table = '<table><thead><tr><th scope="col" colspan="2">Penjualan</th></tr></thead><tbody><tr><td rowspan="2">Beras</td><td>Rp75.000</td></tr><tr><td>Rp68.000</td></tr></tbody></table>';
const cases = [
    ['plain', {text:'Pembukuan penjualan adalah pencatatan transaksi.\n\nPisahkan transaksi tunai dan digital.'}, '<p>'],
    ['markdown', {text:'# Contoh Pembukuan\n\n## Penjualan Harian\n\n| Produk | Total |\n|---|---:|\n| Beras | Rp75.000 |\n\n1. Catat transaksi.\n2. Simpan bukti.\n\nGunakan **aplikasi POS**.'}, '**aplikasi POS**'],
    ['rich-html', {html:'<!--StartFragment--><div class="chat-response"><h2>Penjualan</h2><p><b>Tebal</b> dan <i>miring</i>. <a href="https://example.com/pos" target="_blank">POS</a></p></div><!--EndFragment-->'}, '<strong>Tebal</strong>'],
    ['table', {html:table}, 'colspan="2"'],
    ['headings', {html:'<h1>Contoh</h1><h2>Bagian utama</h2><h3>Rincian</h3><h4>Detail</h4><p>Paragraf pertama.</p><p>Paragraf kedua.</p>'}, '<h4>Detail</h4>'],
    ['lists', {html:'<ol start="3"><li>Catat transaksi.</li><li>Simpan bukti.</li></ol><ul><li>Tunai</li><li>Digital</li></ul>'}, 'start="3"'],
    ['bold-italic', {html:'<p><strong>Aplikasi POS</strong> dan <em>pembukuan</em> <span style="font-weight:700;font-style:italic">rapi</span>.</p>'}, '<em>pembukuan</em>'],
    ['long', {html:'<h2>Usaha</h2>'+('<p>Catat semua transaksi harian agar pembukuan usaha kecil tetap teratur.</p>'.repeat(500))}, 'usaha kecil'],
    ['combined', {html:'<h1>Contoh Pembukuan</h1><h2>Penjualan</h2><p>Gunakan <strong>aplikasi POS</strong> yang <em>rapi</em>.</p>'+table+'<h3>Cara membuat</h3><ol><li>Catat transaksi</li><li>Simpan bukti</li></ol><ul><li>Tunai</li><li>Digital</li></ul><p><a href="https://example.com/pos">Panduan</a></p>'+('<p>Pembukuan harian membantu pemilik usaha mencatat transaksi dengan rapi.</p>'.repeat(100))}, '<td>Rp75.000</td>'],
];
try {
    await page.goto('/admin/login');
    await page.locator('#email').fill('article-save@example.test');
    await page.locator('#password').fill('Article-Save-QA-local-only');
    await page.getByRole('button',{name:'Masuk',exact:true}).click();
    await page.waitForURL('**/admin');
    const run = Date.now();
    let editURL;
    for (const [name, content, expected] of cases) {
        await page.goto('/admin/posts/create');
        await ready();
        await page.locator('#title').fill(`Paste QA ${name} ${run}`);
        await page.locator('#slug').fill(`paste-${name}-${run}`);
        await paste(content);
        const clean = await page.locator('#content').inputValue();
        assert.ok(clean.includes(expected), `${name}: ${clean.slice(0,200)}`);
        if (name === 'markdown') assert.equal(await page.locator('[data-article-editor] h1, [data-article-editor] table').count(), 0);
        if (name === 'table') assert.equal(await page.locator('[data-article-editor] th[scope="col"][colspan="2"]').count(), 1);
        const before = saves.length;
        editURL = await save();
        assert.equal(saves.length, before+1);
        assert.equal(saves.at(-1).patch, false);
        await page.reload(); await ready();
        assert.ok((await page.locator('#content').inputValue()).includes(expected));
        await paste(content);
        await save();
        assert.equal(saves.length, before+2);
        assert.equal(saves.at(-1).patch, true);
        await page.reload(); await ready();
        assert.ok((await page.locator('#content').inputValue()).includes(expected));
        results.push({test:`real Chrome clipboard ${name}: create, edit, reload; exactly one POST per save and PATCH spoof on edit`,pass:true});
    }
    const preview = await context.request.get(editURL.replace(/\/edit$/, '/preview'));
    assert.equal(preview.status(),200);
    assert.ok((await preview.text()).includes('<td>Rp75.000</td>'));
    results.push({test:'server-rendered preview retains mixed clipboard table/headings/list/strong',pass:true});

    await paste({html:'<h2>Belum disimpan</h2><p>Konten error tetap aman.</p>'});
    const body = await page.locator('#content').inputValue();
    const failedURL = page.url();
    for (const status of [403,405,413,419,500]) {
        await page.route(actionPattern, route => route.fulfill({status,contentType:'text/html',body:'<h1>Rejected by QA upstream</h1>'}), {times:1});
        await page.locator('.admin-post-actions button').click();
        await page.locator('[data-post-save-error]').waitFor({state:'visible'});
        assert.equal(page.url(),failedURL);
        assert.equal(await page.locator('#content').inputValue(),body);
        assert.equal(await page.locator('.admin-post-actions button').isEnabled(),true);
        assert.ok(await page.evaluate(() => Object.keys(sessionStorage).some(key => key.startsWith('besofton-article-draft:'))));
    }
    await page.route(actionPattern, route => route.fulfill({status:200,contentType:'text/html',body:'<h1>Unexpected hosting page</h1>'}), {times:1});
    await page.locator('.admin-post-actions button').click();
    await page.getByText('Periksa daftar artikel di tab lain sebelum mencoba kembali untuk menghindari artikel ganda.').waitFor();
    assert.equal(page.url(),failedURL);
    assert.equal(await page.locator('#content').inputValue(),body);
    results.push({test:'403/405/413/419/500 and invalid 200 preserve editor, offer safe errors and keep draft without false success',pass:true});

    // Real Laravel validation (not a mocked response), including absent title.
    await page.locator('#title').fill('');
    await page.locator('[data-post-form]').evaluate(form => {form.noValidate=true;form.requestSubmit();});
    await page.getByText('Artikel belum disimpan. Periksa isian berikut:').waitFor();
    assert.equal(await page.locator('#content').inputValue(),body);
    const layout = await page.locator('[data-post-form]').evaluate(form => ({
        form:form.getBoundingClientRect().width,
        error:form.querySelector('[data-post-save-error]').getBoundingClientRect().width,
        mainLeft:form.querySelector('.admin-post-main').getBoundingClientRect().left,
        formLeft:form.getBoundingClientRect().left,
    }));
    assert.ok(Math.abs(layout.form-layout.error)<1);
    assert.ok(Math.abs(layout.mainLeft-layout.formLeft)<1);
    await page.locator('#title').fill(`Recovered ${run}`);

    // Native Apache denies the POST, then internally routes ErrorDocument to GET.
    await page.route(actionPattern, route => route.continue({headers:{...route.request().headers(),'x-qa-block':'1'}}), {times:1});
    const blocked = page.waitForResponse(response => response.request().method()==='POST' && actionPattern.test(new URL(response.url()).pathname));
    await page.locator('.admin-post-actions button').click();
    const denied = await blocked;
    assert.ok([403,405].includes(denied.status()), `Unexpected Apache simulation status ${denied.status()}`);
    await page.locator('[data-post-save-error]').waitFor({state:'visible'});
    assert.equal(page.url(),failedURL);
    assert.equal(await page.locator('#content').inputValue(),body);
    results.push({test:'native Apache simulated pre-Laravel POST deny + ErrorDocument; editor preserved',status:denied.status(),pass:true});

    page.once('dialog', dialog => dialog.accept());
    await page.reload();
    await page.getByRole('button',{name:'Pulihkan',exact:true}).click();
    await ready();
    assert.equal(await page.locator('#content').inputValue(),body);
    await page.locator('#title').fill(`Recovered ${run}`);
    const beforeDouble = saves.length;
    const doubleSaved = page.waitForEvent('framenavigated',{predicate:frame => frame===page.mainFrame() && frame.url()===failedURL});
    await page.locator('[data-post-form]').evaluate(form => {form.requestSubmit();form.requestSubmit();});
    await doubleSaved;
    await page.waitForLoadState('domcontentloaded');
    await page.waitForFunction(() => !document.querySelector('[data-post-form]')?.dataset.submitting);
    await page.waitForTimeout(500);
    await ready();
    assert.equal(saves.length,beforeDouble+1);
    assert.equal(await page.evaluate(() => Object.keys(sessionStorage).filter(key => key.startsWith('besofton-article-draft:')).length),0);
    assert.equal(await page.locator('#content').inputValue(),body);
    results.push({test:'explicit draft restore after failed save/reload; double submit blocked; confirmed save clears draft',pass:true});
    await page.locator('[name="status"]').selectOption('published');
    await save();
    const slug = await page.locator('#slug').inputValue();
    const publicResponse = await context.request.get('/'+slug);
    assert.equal(publicResponse.status(),200);
    assert.ok((await publicResponse.text()).includes('<h2>Belum disimpan</h2>'));
    results.push({test:'published article remains available in server-rendered public HTML after save',pass:true});
    // Browser same-origin metadata is valid in Laravel 13 even with an old
    // token. APIRequestContext has no browser Sec-Fetch-Site and exercises the
    // real token fallback. UI handling of 419 was exercised above separately.
    const csrfResponse = await context.request.post(failedURL.replace(/\/edit$/,''), {
        form:{_method:'PATCH',_token:'invalid-qa-csrf',title:'Rejected QA',content:body,content_type:'article',status:'draft'},
        headers:{Accept:'application/json'},
    });
    assert.equal(csrfResponse.status(),419);
    await context.clearCookies();
    const expiredResponse = page.waitForResponse(response=>response.request().method()==='POST' && actionPattern.test(new URL(response.url()).pathname));
    await page.locator('.admin-post-actions button').click();
    assert.equal((await expiredResponse).status(),401);
    await page.locator('[data-post-save-error]').waitFor({state:'visible'});
    assert.equal(page.url(),failedURL);
    assert.equal(await page.locator('#content').inputValue(),body);
    results.push({test:'real API token fallback rejects invalid CSRF (419); expired same-origin UI session returns 401 with content intact',pass:true});
    await page.setViewportSize({width:390,height:844});
    await page.waitForTimeout(3500);
    await page.locator('[data-post-save-error]').scrollIntoViewIfNeeded();
    await page.locator('[data-post-save-error]').focus();
    await page.screenshot({path:'storage/app/article-save-qa/error-390.png'});
    await page.screenshot({path:'storage/app/article-save-qa/editor-390.png',fullPage:true});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth),false);
    assert.deepEqual(errors,[]);
} finally {
    await mkdir('storage/app/article-save-qa',{recursive:true});
    await writeFile('storage/app/article-save-qa/browser-results.json',JSON.stringify({results,errors,saves},null,2));
    console.log(JSON.stringify({results,errors,saveRequests:saves.length},null,2));
    await browser.close();
}
