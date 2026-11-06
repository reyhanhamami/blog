import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const playwright = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const browserName = process.env.LIVEWIRE_QA_BROWSER || 'chromium';
const baseURL = process.env.THEME_QA_URL || 'http://127.0.0.1:8767';
if (!['localhost', '127.0.0.1'].includes(new URL(baseURL).hostname)) throw new Error('QA writes must use isolated localhost.');
const executablePath = browserName === 'firefox' ? process.env.LIVEWIRE_QA_FIREFOX : process.env.THEME_QA_CHROMIUM;
const browser = await playwright[browserName].launch({ headless: true, executablePath });
const context = await browser.newContext({ baseURL });
await context.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
const page = await context.newPage();
const publicPage = await context.newPage();
const errors = [];
const requests = [];
const isUpdate = url => /\/livewire-[a-f0-9]{8}\/update\/?$/.test(new URL(url).pathname);
for (const tab of [page, publicPage]) {
    tab.on('pageerror', error => errors.push(error.message));
    tab.on('console', message => { if (message.type() === 'error' || /multiple instances/i.test(message.text())) errors.push(message.text()); });
    tab.on('request', request => {
        if (!isUpdate(request.url())) return;
        const payload = request.postDataJSON();
        // Counts only. Never store snapshots, payload values, tokens or cookies.
        requests.push({ path: new URL(request.url()).pathname, method: request.method(), redirected: !!request.redirectedFrom(), saveCalls: (payload?.components ?? []).flatMap(c => c.calls ?? []).filter(c => c.method === 'save').length });
    });
}
const out = 'storage/app/livewire-transport-qa';
await mkdir(out, { recursive: true });
const ready = () => page.waitForFunction(() => document.querySelector('[data-theme-preview]')?.style.getPropertyValue('--public-primary'));
const login = async (tab, email) => {
    await tab.goto('/admin/login');
    await tab.locator('#email').fill(email);
    await tab.locator('#password').fill('Theme-QA-local-only');
    await Promise.all([tab.waitForURL('**/admin'), tab.getByRole('button', { name: 'Masuk', exact: true }).click()]);
};
let cdp;
const listeners = async () => {
    if (!cdp) return null;
    const count = async (expression, type) => {
        const { result } = await cdp.send('Runtime.evaluate', { expression });
        const { listeners } = await cdp.send('DOMDebugger.getEventListeners', { objectId: result.objectId });
        await cdp.send('Runtime.releaseObject', { objectId: result.objectId });
        return listeners.filter(listener => listener.type === type).length;
    };
    return { unload: await count('window', 'beforeunload'), navigate: await count('document', 'livewire:navigate') };
};
const save = async expected => {
    const before = requests.length;
    const responsePromise = page.waitForResponse(response => isUpdate(response.url()));
    await page.getByRole('button', { name: 'Simpan Tema', exact: true }).click();
    const response = await responsePromise;
    assert.equal(response.request().method(), 'POST');
    assert.equal(response.status(), 200);
    assert.equal(response.headers().location, undefined);
    assert.equal(response.request().redirectedFrom(), null);
    await page.getByText('Pengaturan tampilan website berhasil diperbarui.', { exact: true }).waitFor();
    await page.waitForLoadState('networkidle');
    assert.equal(requests.length - before, 1, 'one Livewire request per Save');
    assert.equal(requests.at(-1).saveCalls, 1, 'one save action after navigation/history');
    assert.equal(new URL(page.url()).pathname, '/admin/settings');
    await publicPage.goto('/');
    assert.equal(await publicPage.locator('body').evaluate(node => getComputedStyle(node).getPropertyValue('--public-primary').trim()), expected);
};
try {
    const response = await page.goto('/admin/login');
    const server = response.headers().server;
    assert.match(server, /Apache/i, 'test the actual .htaccess with native Apache');
    await login(page, 'theme-qa@example.test');
    await page.goto('/admin/settings');
    await ready();
    if (browserName === 'chromium') cdp = await context.newCDPSession(page);
    const baselineListeners = await listeners();
    const initialUri = await page.locator('script[data-update-uri]').getAttribute('data-update-uri');
    assert.equal(await page.locator('script[data-update-uri]').count(), 1);

    for (const preset of ['Besofton Classic', 'Modern Blue', 'Forest Green', 'Red White']) {
        await page.locator('#public-theme-preset').selectOption(preset);
        await save(await page.locator('#theme-primary').inputValue());
    }
    await page.locator('#theme-primary').fill('#b71c1c');
    await save('#b71c1c');
    await page.reload();
    await ready();
    assert.equal(await page.locator('#theme-primary').inputValue(), '#b71c1c');
    assert.equal(await page.locator('script[data-update-uri]').getAttribute('data-update-uri'), initialUri);
    for (let index = 0; index < 3; index++) {
        await Promise.all([page.waitForURL('**/admin'), page.locator(`a.cms-nav[href="${baseURL}/admin"]`).click()]);
        await page.goBack();
        await page.waitForURL('**/admin/settings');
        await ready();
        assert.deepEqual(await listeners(), baselineListeners, 'listener count stable after Back');
        await page.goForward();
        await page.waitForURL('**/admin');
        await Promise.all([page.waitForURL('**/admin/settings'), page.locator(`a.cms-nav[href="${baseURL}/admin/settings"]`).click()]);
        await ready();
        assert.deepEqual(await listeners(), baselineListeners, 'listener count stable after wire:navigate');
        const color = ['#b71c1c', '#aa2020', '#a91d1d'][index];
        await page.locator('#theme-primary').fill(color);
        await save(color);
    }

    // Branding/homepage uses the existing PATCH form, not a Livewire component.
    const regular = page.locator(`form[action="${baseURL}/admin/settings"]`);
    assert.equal(await regular.getAttribute('wire:submit'), null);
    assert.equal(await regular.locator('input[name=_method]').inputValue(), 'PATCH');
    const regularResponse = page.waitForResponse(r => new URL(r.url()).pathname === '/admin/settings' && r.request().method() === 'POST');
    await page.getByRole('button', { name: 'Simpan pengaturan', exact: true }).click();
    assert.equal((await regularResponse).status(), 302);
    await ready();
    assert.equal(await page.locator('#theme-primary').inputValue(), '#a91d1d');

    const adminSnapshot = await page.locator('#public-theme').getAttribute('wire:snapshot');
    const csrfPayload = { components: [{ snapshot: adminSnapshot, updates: {}, calls: [{ path: '', method: 'save', params: [] }] }] };
    const csrfFailure = await context.request.post(initialUri, { headers: { 'X-Livewire': '1', 'Accept': '*/*' }, data: csrfPayload, maxRedirects: 0 });
    assert.equal(csrfFailure.status(), 419, 'real web CSRF protection remains enabled');

    const guest = await browser.newContext({ baseURL });
    const guestPage = await guest.newPage();
    await guest.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
    await guestPage.goto('/');
    const token = await guestPage.locator('script[data-csrf]').getAttribute('data-csrf');
    const denied = await guest.request.post(initialUri, { headers: { 'X-Livewire': '1', 'Accept': 'application/json' }, data: { ...csrfPayload, _token: token }, maxRedirects: 0 });
    assert.equal(denied.status(), 401, 'expired/guest session cannot save signed admin snapshot');
    assert.equal(denied.headers().location, undefined);

    const editor = await browser.newContext({ baseURL });
    const editorPage = await editor.newPage();
    await editor.route('https://fonts.googleapis.com/**', route => route.fulfill({ contentType: 'text/css', body: '' }));
    await login(editorPage, 'theme-editor-qa@example.test');
    const editorToken = await editorPage.locator('script[data-csrf]').getAttribute('data-csrf');
    const forbidden = await editor.request.post(initialUri, { headers: { 'X-Livewire': '1', 'Accept': 'application/json' }, data: { ...csrfPayload, _token: editorToken }, maxRedirects: 0 });
    assert.equal(forbidden.status(), 403, 'editor without settings permission denied');
    assert.equal(forbidden.headers().location, undefined);
    await editor.close();

    // A read-only public refresh exercises Livewire outside the theme editor.
    const publicSnapshot = await guestPage.locator('[wire\\:snapshot]').first().getAttribute('wire:snapshot');
    const refresh = { _token: token, components: [{ snapshot: publicSnapshot, updates: {}, calls: [{ path: '', method: '$refresh', params: [] }] }] };
    const slash = await guest.request.post(initialUri + '/', { headers: { 'X-Livewire': '1', 'Accept': 'application/json' }, data: refresh, maxRedirects: 0 });
    assert.equal(slash.status(), 200, 'Apache does not redirect POST trailing slash');
    assert.equal(slash.headers().location, undefined);
    const canonical = await guest.request.post(initialUri, { headers: { 'X-Livewire': '1', 'Accept': 'application/json' }, data: refresh, maxRedirects: 0 });
    assert.equal(canonical.status(), 200);
    const getOnly = await guest.request.get(initialUri, { headers: { 'X-Livewire': '1', 'Accept': 'application/json' }, maxRedirects: 0 });
    assert.equal(getOnly.status(), 405);
    const pageRedirect = await guest.request.get(baseURL + '/admin/settings/', { maxRedirects: 0 });
    assert.equal(pageRedirect.status(), 301, 'GET page canonicalization remains unchanged');
    await guest.close();

    assert.ok(requests.every(request => request.method === 'POST' && !request.redirected));
    assert.deepEqual(errors, []);
    await writeFile(`${out}/${browserName}-results.json`, JSON.stringify({ server, browser: browserName, saveRequests: requests.filter(r => r.saveCalls > 0).length, requests, listenerBaseline: baselineListeners, listenerFinal: await listeners(), csrfStatus: 419, guestStatus: 401, forbiddenStatus: 403, canonicalPost: 200, trailingSlashPost: 200, unsupportedGet: 405, regularSettings: 'PATCH form with normal 302 PRG', errors }, null, 2));
    console.log(`${browserName}: real Apache Livewire POST/save, Back/Forward, listener cleanup, CSRF, guest rejection, public refresh and trailing slash passed.`);
} catch (error) {
    await page.screenshot({ path: `${out}/${browserName}-failure.png` }).catch(() => {});
    await writeFile(`${out}/${browserName}-failure.json`, JSON.stringify({ message: error.message, requests, errors }, null, 2));
    throw error;
} finally {
    await browser.close();
}
