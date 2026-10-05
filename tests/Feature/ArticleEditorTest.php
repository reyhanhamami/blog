<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

test('editor sanitizer keeps semantic headings image metadata table and escaped code', function () {
    $html = '<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>'
        .'<figure class="article-image article-image--medium article-image--center"><img src="/uploads/photo.png" alt="Diagram" width="560" height="320"><figcaption>Penjelasan</figcaption></figure>'
        .'<table><thead><tr><th>Label</th></tr></thead><tbody><tr><td>Nilai</td></tr></tbody></table>'
        .'<pre><code class="language-php">&lt;script&gt;alert(1)&lt;/script&gt;</code></pre>';
    $safe = app(HtmlSanitizer::class)->clean($html);

    foreach (range(1, 6) as $level) {
        expect($safe)->toContain('<h'.$level.'>H'.$level.'</h'.$level.'>');
    }
    expect($safe)->toContain('article-image--medium', 'article-image--center', 'alt="Diagram"', 'width="560"', '<figcaption>Penjelasan</figcaption>', '<table>', '<th>Label</th>', 'language-php', '&lt;script&gt;alert(1)&lt;/script&gt;');
    expect($safe)->not->toContain('<script>');
});

test('editor sanitizer removes active content and junk while preserving pasted text', function () {
    $html = '<div class="WordSection1"><p style="mso-font-size:12px" onclick="evil()">A <span style="font-weight:bold;font-family:Arial">tebal</span> <span style="font-style:italic">miring</span></p></div>'
        .'<a href="javascript:alert(1)" target="_blank" rel="opener" onclick="evil()">Buruk</a>'
        .'<a href="https://example.test" target="_blank" rel="opener">Baik</a>'
        .'<figure class="article-image article-image--huge article-image--center"><img src="data:image/png;base64,evil" onerror="evil()"></figure>'
        .'<script>alert(1)</script><!--comment-->';
    $safe = app(HtmlSanitizer::class)->clean($html);

    expect($safe)->toContain('A ', '<strong>tebal</strong>', '<em>miring</em>', '>Buruk</a>', 'target="_blank"', 'rel="noopener noreferrer"');
    expect($safe)->not->toContain('WordSection', 'mso-', 'font-family', 'onclick', 'onerror', 'javascript:', 'data:image', 'article-image--huge', '<script', 'comment');
});

test('create and edit share editor and preserve submitted semantic HTML', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()->assertSee('data-article-editor-field', false)->assertSee('data-editor-block', false)->assertSee('data-editor-image-dialog-size', false);
    $content = '<h2>Mulai</h2><p><strong>Tebal</strong> dan <code>inline</code>.</p><figure class="article-image article-image--small article-image--right"><img src="https://example.test/photo.jpg" alt="Foto" width="320" height="200"><figcaption>Caption</figcaption></figure>';
    $this->post(route('admin.posts.store'), ['title' => 'Panduan editor', 'slug' => '', 'content' => $content, 'status' => 'published', 'content_type' => 'tutorial'])->assertRedirect();
    $post = Post::where('slug', 'panduan-editor')->firstOrFail();
    expect($post->content)->toContain('<h2>Mulai</h2>', 'article-image--small', 'article-image--right', '<figcaption>Caption</figcaption>');
    $this->get(route('admin.posts.edit', $post))->assertOk()->assertSee('data-article-editor-field', false)->assertSee('Caption');
    $this->get('/panduan-editor')->assertOk()->assertSee('article-image--right', false)->assertSee('Caption');
});

test('editor upload uses media library validation and returns selected image metadata', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $response = $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('diagram.png', 320, 180), 'alt_text' => 'Diagram editor', 'caption' => 'Penjelasan']);
    $response->assertCreated()->assertJsonPath('alt', 'Diagram editor')->assertJsonPath('caption', 'Penjelasan');
    $path = Media::firstOrFail()->path;
    expect(File::exists(public_path($path)))->toBeTrue();
    File::delete(public_path($path));

    $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('fake.jpg', '<html>not image</html>'), 'alt_text' => 'Fake'])->assertUnprocessable()->assertJsonValidationErrors('file');
});
