<?php

use App\Http\Middleware\LogArticleSaveFailures;
use App\Models\Post;
use App\Models\User;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

function articleSavePayload(string $content = '<p>Isi artikel.</p>'): array
{
    return ['title' => 'Pembukuan penjualan harian', 'slug' => 'pembukuan-save-qa', 'content' => $content, 'content_type' => 'article', 'status' => 'draft'];
}

test('article validation redirects to its editor even when the referrer is the action URL', function (bool $editing) {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = $editing ? Post::create(['title' => 'Existing', 'slug' => 'existing-save', 'status' => 'draft', 'content' => '<p>Original</p>', 'created_by' => $admin->id]) : null;
    $action = $post ? route('admin.posts.update', $post) : route('admin.posts.store');
    $editor = $post ? route('admin.posts.edit', $post) : route('admin.posts.create');
    $payload = articleSavePayload('<h2>Konten tetap ada</h2><p>Jangan hilang.</p>');
    $payload['title'] = '';
    $this->actingAs($admin)->withHeader('Referer', $action);
    $response = $post ? $this->patch($action, $payload) : $this->post($action, $payload);
    $response->assertRedirect($editor)->assertSessionHasErrors('title')->assertSessionHasInput('content', $payload['content']);
    $this->get($editor)->assertOk()->assertSee('Konten tetap ada');
    if ($post) {
        expect($post->fresh()->content)->toBe('<p>Original</p>');
    }
})->with([false, true]);

test('manual validation errors also return to the editor and preserve pasted content', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = Post::create(['title' => 'Existing', 'slug' => 'existing-save', 'status' => 'draft', 'created_by' => $admin->id]);
    $payload = articleSavePayload();
    $payload['slug'] = 'admin';
    $this->actingAs($admin)->withHeader('Referer', route('admin.posts.update', $post))->patch(route('admin.posts.update', $post), $payload)
        ->assertRedirect(route('admin.posts.edit', $post))->assertSessionHasErrors('slug')->assertSessionHasInput('content', $payload['content']);
});

test('conventional article create and edit return only to the GET editor', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $response = $this->post(route('admin.posts.store'), articleSavePayload());
    $post = Post::where('slug', 'pembukuan-save-qa')->firstOrFail();
    $response->assertRedirect(route('admin.posts.edit', $post));
    $this->patch(route('admin.posts.update', $post), articleSavePayload('<h2>Updated</h2>'))->assertRedirect(route('admin.posts.edit', $post));
    $this->get(route('admin.posts.edit', $post))->assertOk();
    $this->get(route('admin.posts.update', $post))->assertStatus(405);
    expect($post->fresh()->content)->toBe('<h2>Updated</h2>');
});

test('pasted content survives create edit and reload with server sanitization', function (string $content, string $expected) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $created = $this->postJson(route('admin.posts.store'), articleSavePayload($content));
    $created->assertSuccessful();
    $post = Post::where('slug', 'pembukuan-save-qa')->firstOrFail();
    $created->assertJsonPath('redirect', route('admin.posts.edit', $post));
    $this->get(route('admin.posts.edit', $post))->assertOk()->assertSee('data-seo-analysis', false);
    $this->patchJson(route('admin.posts.update', $post), articleSavePayload($content))->assertOk()->assertJsonPath('redirect', route('admin.posts.edit', $post));
    expect($post->fresh()->content)->toContain($expected)->not->toContain('<script', 'onclick=', 'onerror=', 'javascript:', 'data-evil');
})->with([
    'plain' => ['Teks biasa untuk artikel.', 'Teks biasa untuk artikel.'],
    'markdown literal' => ["# Contoh Pembukuan\n\n## Transaksi\n\n| Produk | Total |\n|---|---:|\n| Beras | Rp75.000 |\n\n1. Catat transaksi.\n\n**Aplikasi POS**", '**Aplikasi POS**'],
    'rich clipboard' => ['<!--StartFragment--><div data-evil="1"><h2>Penjualan</h2><p><b>Tebal</b> dan <i>miring</i> <a href="https://example.com" target="_blank" onclick="alert(1)">POS</a></p></div><!--EndFragment-->', '<strong>Tebal</strong>'],
    'table with spans' => ['<table><thead><tr><th scope="col" colspan="2">Penjualan</th></tr></thead><tbody><tr><td rowspan="2">Beras</td><td>Rp75.000</td></tr><tr><td>Rp68.000</td></tr></tbody></table>', '<th scope="col" colspan="2">'],
    'headings lists' => ['<h1>Contoh</h1><h2>Utama</h2><h3>Rinci</h3><h4>Detail</h4><ol start="3"><li>Catat</li></ol><ul><li>Simpan</li></ul>', '<ol start="3">'],
    'long mixed' => ['<h2>Pembukuan</h2>'.str_repeat('<p><strong>Catat</strong> transaksi harian yang rapi untuk usaha kecil.</p>', 550).'<table><tr><td>Total</td><td>188000</td></tr></table><ol><li>Simpan bukti</li></ol>', '<td>188000</td>'],
    'unsafe markup' => ['<h2>Aman</h2><script>alert(1)</script><p onclick="alert(1)">Isi<img src="javascript:alert(1)" onerror="alert(1)"><a href="javascript:alert(1)">link</a></p>', '<h2>Aman</h2>'],
]);

test('invalid JSON save preserves the database and returns field errors without redirect', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $post = Post::create(['title' => 'Original', 'slug' => 'original-save', 'status' => 'draft', 'content' => '<p>Original</p>', 'created_by' => $admin->id]);
    $data = articleSavePayload();
    $data['title'] = '';
    $this->actingAs($admin)->patchJson(route('admin.posts.update', $post), $data)->assertUnprocessable()->assertJsonValidationErrors('title')->assertHeaderMissing('Location');
    expect($post->fresh()->content)->toBe('<p>Original</p>');
});

test('unauthorized and expired sessions cannot save articles', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))->postJson(route('admin.posts.store'), articleSavePayload())->assertForbidden()->assertHeaderMissing('Location');
    expect(Post::count())->toBe(0);
})->with(['reader']);

test('guest JSON save never redirects to an action URL', function () {
    $this->postJson(route('admin.posts.store'), articleSavePayload())->assertUnauthorized()->assertHeaderMissing('Location');
    expect(Post::count())->toBe(0);
});

test('article saving retains real CSRF protection', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->app->instance('env', 'production');
    $this->withSession(['_token' => 'csrf-save-fixture'])->postJson(route('admin.posts.store'), articleSavePayload())->assertStatus(419)->assertHeaderMissing('Location');
    expect(Post::count())->toBe(0);
});

test('sanitizer retains bounded table semantics without allowing handlers or CSS', function () {
    $html = app(HtmlSanitizer::class)->clean('<table style="color:red"><tr><th scope="col" colspan="2" onclick="evil()">Total</th><td rowspan="99999">X</td></tr></table>');
    expect($html)->toContain('scope="col"', 'colspan="2"')->not->toContain('onclick', 'style=', 'rowspan=');
});

test('unsafe old input is escaped or sanitized when validation redisplays the editor', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $payload = articleSavePayload('<script>window.stolen=1</script><h2 onclick="evil()">Rencana</h2>');
    $payload['title'] = '';
    $this->post(route('admin.posts.store'), $payload)->assertRedirect(route('admin.posts.create'));
    $html = $this->get(route('admin.posts.create'))->assertOk()->getContent();
    $doc = new DOMDocument;
    @$doc->loadHTML($html);
    $xpath = new DOMXPath($doc);
    expect($xpath->query('//*[@data-article-editor]//script|//*[@data-article-editor]//*[@onclick]')->length)->toBe(0);
    expect($xpath->query('//textarea[@id="content"]')->item(0)->textContent)->toBe($payload['content']);
});

test('save diagnostics distinguish spoofed PATCH and Apache error status without logging article secrets', function () {
    Log::spy();
    $request = Request::create('/admin/posts/5?secret=query', 'POST', ['_method' => 'PATCH', '_token' => 'secret-token', 'content' => 'secret-article'], server: ['REDIRECT_STATUS' => '403', 'CONTENT_LENGTH' => '123']);
    Request::enableHttpMethodParameterOverride();
    $middleware = new LogArticleSaveFailures;
    $response = response()->json(['message' => 'Rejected'], 405);
    expect($middleware->handle($request, fn () => $response))->toBe($response);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $message === 'Article save transport failure.'
        && $context['method'] === 'PATCH' && $context['server_method'] === 'POST'
        && $context['internal_redirect_status'] === 403 && $context['content_length'] === 123
        && ! str_contains(json_encode($context), 'secret'));
});
