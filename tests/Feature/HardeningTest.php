<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

function qaUser(string $role = 'superadmin'): User
{
    return User::factory()->create(['role' => $role]);
}

function qaPost(array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Artikel QA', 'slug' => 'artikel-qa', 'status' => 'published',
        'published_at' => now()->subMinute(), 'content' => '<p>Isi artikel QA.</p>',
        'content_plain' => 'Isi artikel QA.',
    ], $attributes));
}

test('article form persists all critical fields and renders server HTML after publication', function () {
    $admin = qaUser();
    $category = Category::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
    $topic = Topic::create(['name' => 'Backend', 'slug' => 'backend']);
    $author = Author::create(['name' => 'Dina', 'slug' => 'dina']);
    $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()->assertSee('data-post-form', false);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Panduan QA', 'slug' => 'panduan-qa', 'excerpt' => 'Ringkasan QA',
        'content' => '<h2>Langkah pertama</h2><p>Isi utama.</p><pre><code class="language-php">echo 1;</code></pre><p>[youtube:abcdefghijk]</p>',
        'category_id' => $category->id, 'author_id' => $author->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
        'featured_image' => 'https://example.test/cover.jpg', 'featured_image_alt' => 'Sampul QA',
        'direct_answer' => 'Jawaban langsung', 'key_takeaways' => "Poin satu\nPoin dua",
        'seo_title' => 'Judul SEO QA', 'seo_description' => 'Deskripsi SEO QA',
        'og_title' => 'Judul OG QA', 'og_description' => 'Deskripsi OG QA', 'og_image' => 'https://example.test/og.jpg',
        'content_type' => 'tutorial', 'status' => 'draft',
    ])->assertRedirect();
    $post = Post::where('slug', 'panduan-qa')->firstOrFail();
    expect($post->category_id)->toBe($category->id);
    expect($post->tags->modelKeys())->toBe([$tag->id]);
    expect($post->topics->modelKeys())->toBe([$topic->id]);
    $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->assertSee('Judul SEO QA')->assertSee('Sampul QA')->assertSee('abcdefghijk');
    $this->get('/panduan-qa')->assertNotFound();
    $this->actingAs($admin)->get(route('admin.posts.preview', $post))->assertOk()->assertSee('noindex,nofollow');
    $this->actingAs($admin)->post(route('admin.posts.sources.store', $post), ['title' => 'Dokumentasi resmi', 'url' => 'https://example.test/docs', 'publisher' => 'Penerbit', 'sort_order' => 1])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.posts.update', $post), [
        'title' => $post->title, 'slug' => $post->slug, 'excerpt' => $post->excerpt, 'content' => $post->content,
        'category_id' => $category->id, 'author_id' => $author->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
        'featured_image' => $post->featured_image, 'featured_image_alt' => $post->featured_image_alt,
        'direct_answer' => $post->direct_answer, 'key_takeaways' => $post->key_takeaways,
        'seo_title' => $post->seo_title, 'seo_description' => $post->seo_description,
        'og_title' => $post->og_title, 'og_description' => $post->og_description, 'og_image' => $post->og_image,
        'content_type' => 'tutorial', 'status' => 'published',
    ])->assertRedirect();
    $html = $this->get('/panduan-qa')->assertOk()->getContent();
    expect($html)->toContain('<h1', 'Isi utama.', 'language-php', 'data-youtube="abcdefghijk"', 'Dokumentasi resmi', 'Poin satu', 'Dina');
    expect(substr_count($html, '<link rel="canonical"'))->toBe(1);
    expect(preg_match_all('/<h1\b/', $html))->toBe(1);
    expect($html)->toContain('<title>Judul SEO QA | Besofton Insights</title>', 'property="og:image" content="https://example.test/og.jpg"');
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    $schemas = array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
    $article = collect($schemas)->firstWhere('@type', 'Article');
    expect($article['headline'])->toBe('Panduan QA');
    expect($article['image'])->toBe('https://example.test/cover.jpg');
    expect($article['author']['name'])->toBe('Dina');
    expect(collect($schemas)->pluck('@type'))->toContain('BreadcrumbList');
});

test('sanitizer removes scripts event handlers dangerous URLs and arbitrary iframes', function () {
    $html = '<h2>Judul</h2><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><iframe src="https://evil.example"></iframe><pre><code class="language-php">echo 1;</code></pre>';
    $safe = app(HtmlSanitizer::class)->clean($html);
    expect($safe)->toContain('<h2>Judul</h2>', 'language-php');
    expect($safe)->not->toContain('<script', 'onerror', 'javascript:', '<iframe', 'src="x"');
});

test('sitemap excludes draft future noindex soft deleted and preview URLs', function () {
    qaPost();
    qaPost(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'published_at' => null]);
    qaPost(['title' => 'Future', 'slug' => 'future', 'published_at' => now()->addDay()]);
    qaPost(['title' => 'Noindex', 'slug' => 'noindex', 'noindex' => true]);
    $deleted = qaPost(['title' => 'Deleted', 'slug' => 'deleted']);
    $deleted->delete();
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/artikel-qa');
    expect($xml)->not->toContain('/draft', '/future', '/noindex', '/deleted', '/admin', '/preview');
});

test('robots disallows staging and advertises sitemap only in production', function () {
    config()->set('app.env', 'staging');
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
    config()->set('app.env', 'production');
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
});