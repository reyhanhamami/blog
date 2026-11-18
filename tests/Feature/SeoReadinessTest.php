<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use App\Providers\AppServiceProvider;
use App\Services\PublicSitemap;
use App\Support\PublicImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config(['app.env' => 'production', 'app.url' => 'https://blog.besofton.id']);
    app()->instance('env', 'production');
    (new AppServiceProvider(app()))->boot();
});

function indexingPost(array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Artikel indeks', 'slug' => 'artikel-indeks', 'status' => 'published',
        'published_at' => now()->subDay(), 'content' => '<h2>Isi SSR</h2><p>Artikel tersedia tanpa JavaScript.</p>',
        'content_plain' => 'Isi SSR Artikel tersedia tanpa JavaScript.',
    ], $attributes));
}

function sitemapLocations(string $xml): array
{
    $document = simplexml_load_string($xml);
    expect($document)->not->toBeFalse();

    return array_map(fn ($node) => (string) $node, $document->xpath('//*[local-name()="loc"]'));
}

test('production published HTML is indexable with one HTTPS canonical and server rendered content', function () {
    indexingPost();
    foreach (['/', '/artikel-indeks'] as $path) {
        $response = $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $response->assertSee('<meta name="robots" content="index,follow">', false);
        preg_match_all('/<link rel="canonical" href="([^"]+)">/', $response->getContent(), $canonicals);
        expect($canonicals[1])->toHaveCount(1);
        expect($canonicals[1][0])->toStartWith('https://blog.besofton.id');
    }
    $this->get('/artikel-indeks')->assertSee('<h2>Isi SSR</h2>', false);
    $this->get('/artikel-indeks?page=2')->assertSee('rel="canonical" href="https://blog.besofton.id/artikel-indeks"', false);
    $this->get('/')->assertSee('href="https://blog.besofton.id/artikel-indeks"', false);
});

test('sitemap excludes unpublished scheduled future deleted and noindex articles and preserves editorial dates', function () {
    $post = indexingPost(['updated_at' => now()->subHours(6)]);
    $modified = $post->updated_at->toAtomString();
    foreach ([['slug' => 'draft-hidden', 'status' => 'draft'], ['slug' => 'scheduled-hidden', 'status' => 'scheduled', 'scheduled_at' => now()->addDay()], ['slug' => 'future-hidden', 'published_at' => now()->addDay()], ['slug' => 'excluded-hidden', 'noindex' => true]] as $attributes) {
        indexingPost($attributes);
    }
    indexingPost(['slug' => 'deleted-hidden'])->delete();
    $this->get('/artikel-indeks')->assertOk();
    expect($post->fresh()->updated_at->toAtomString())->toBe($modified);
    $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
    expect(sitemapLocations($xml))->toContain('https://blog.besofton.id/artikel-indeks');
    expect($xml)->toContain($modified)->not->toContain('hidden');
    foreach (['draft-hidden', 'scheduled-hidden', 'future-hidden', 'deleted-hidden'] as $slug) {
        $this->get('/'.$slug)->assertNotFound();
    }
    $this->get('/excluded-hidden')->assertOk()->assertSee('content="noindex,nofollow"', false);
    $home = simplexml_load_string($xml)->xpath('//*[local-name()="url"][1]')[0];
    expect(isset($home->lastmod))->toBeFalse();
});

test('only useful active taxonomy archives enter sitemap and empty archives are noindex', function () {
    $category = Category::create(['name' => 'Aktif', 'slug' => 'aktif', 'is_active' => true]);
    $inactive = Category::create(['name' => 'Nonaktif', 'slug' => 'nonaktif', 'is_active' => false]);
    Category::create(['name' => 'Kosong', 'slug' => 'kosong', 'is_active' => true]);
    $topic = Topic::create(['name' => 'Topik', 'slug' => 'topik-aktif']);
    Topic::create(['name' => 'Kosong', 'slug' => 'topik-kosong']);
    $author = Author::create(['name' => 'Penulis', 'slug' => 'penulis']);
    $post = indexingPost(['category_id' => $category->id, 'author_id' => $author->id]);
    $post->topics()->attach($topic);
    indexingPost(['slug' => 'nonaktif-post', 'category_id' => $inactive->id]);
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    foreach (['/kategori/aktif', '/topik/topik-aktif', '/author/penulis'] as $path) {
        expect(sitemapLocations($xml))->toContain('https://blog.besofton.id'.$path);
        $this->get($path)->assertOk()->assertSee('content="index,follow"', false)->assertSee('/artikel-indeks');
    }
    expect($xml)->not->toContain('/kategori/nonaktif', '/kategori/kosong', '/topik/topik-kosong');
    $this->get('/kategori/nonaktif')->assertNotFound();
    $this->get('/nonaktif-post')->assertOk()->assertDontSee('/kategori/nonaktif');
    $this->get('/kategori/kosong')->assertSee('content="noindex,follow"', false);
    $this->get('/topik/topik-kosong')->assertSee('content="noindex,follow"', false);
    $this->get('/kategori/aktif?sort=popular')->assertSee('content="noindex,follow"', false);
});

test('topic recommendations link only archives sharing a published indexable article', function () {
    $topic = Topic::create(['name' => 'Topik utama', 'slug' => 'utama']);
    $shared = Topic::create(['name' => 'Topik beririsan', 'slug' => 'beririsan']);
    $unrelated = Topic::create(['name' => 'Topik tidak berkaitan', 'slug' => 'tidak-berkaitan']);
    indexingPost()->topics()->attach([$topic->id, $shared->id]);
    indexingPost(['slug' => 'lain'])->topics()->attach($unrelated);
    $this->get('/topik/utama')->assertOk()->assertSee('Topik beririsan')->assertDontSee('Topik tidak berkaitan');
});

test('sitemap covers public learning video static pages and avoids route collisions and duplicate URLs', function () {
    indexingPost(['slug' => 'shared']);
    indexingPost(['slug' => 'login']);
    indexingPost(['slug' => 'about']); // This named route requires a published Page instead.
    Page::create(['title' => 'Halaman', 'slug' => 'shared', 'content' => '<p>Halaman</p>', 'is_published' => true]);
    Page::create(['title' => 'Privat', 'slug' => 'private-page', 'content' => '', 'is_published' => false]);
    Page::create(['title' => 'Bentrok', 'slug' => 'register', 'content' => '', 'is_published' => true]);
    Video::create(['title' => 'Video publik', 'slug' => 'video-publik', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->subDay()]);
    Video::create(['title' => 'Video nanti', 'slug' => 'video-nanti', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->addDay()]);
    Course::create(['title' => 'Kelas publik', 'slug' => 'kelas-publik', 'status' => 'published']);
    LearningPath::create(['title' => 'Jalur publik', 'slug' => 'jalur-publik', 'status' => 'published']);
    Quiz::create(['title' => 'Kuis publik', 'slug' => 'kuis-publik', 'status' => 'published']);
    $locations = sitemapLocations($this->get('/sitemap.xml')->assertOk()->getContent());
    expect($locations)->toHaveCount(count(array_unique($locations)));
    foreach ($locations as $location) {
        $this->get($location)->assertOk()->assertDontSee('content="noindex', false);
    }
    foreach (['/shared', '/video/video-publik', '/kelas/kelas-publik', '/belajar/jalur-publik', '/kuis/kuis-publik'] as $path) {
        expect($locations)->toContain('https://blog.besofton.id'.$path);
    }
    expect($locations)->not->toContain('https://blog.besofton.id/login', 'https://blog.besofton.id/register', 'https://blog.besofton.id/about', 'https://blog.besofton.id/private-page', 'https://blog.besofton.id/video/video-nanti');
    $this->get('/video/video-publik')->assertSee('"@type":"WebPage"', false)->assertDontSee('"uploadDate"', false);
});

test('sitemap becomes a bounded index only when needed and invalid shards are 404', function () {
    foreach (range(1, 5) as $number) {
        indexingPost(['slug' => 'bounded-'.$number]);
    }
    app()->instance(PublicSitemap::class, new PublicSitemap(2));
    $index = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($index)->toContain('<sitemapindex');
    $all = [];
    foreach (sitemapLocations($index) as $location) {
        $urls = sitemapLocations($this->get($location)->assertOk()->getContent());
        expect(count($urls))->toBeLessThanOrEqual(2);
        $all = array_merge($all, $urls);
    }
    expect($all)->toHaveCount(6)->toHaveCount(count(array_unique($all)));
    $this->get('/sitemaps/posts/4.xml')->assertNotFound();
    $this->get('/sitemaps/invalid/1.xml')->assertNotFound();
});

test('robots permits public crawling and staging denies it while search stays noindex', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://blog.besofton.id/sitemap.xml', false)
        ->assertDontSee('Disallow: /cari')->assertDontSee("Disallow: /\n", false);
    $this->get('/cari?q=Laravel')->assertOk()->assertSee('content="noindex,follow"', false);
    config(['app.env' => 'staging']);
    $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    $this->get('/')->assertSee('content="noindex,nofollow"', false);
});

test('private endpoints retain authentication and noindex response headers', function () {
    $this->get('/akun')->assertRedirect('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/admin')->assertRedirect('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    foreach (['/login', '/register', '/admin/login'] as $path) {
        $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
    $draft = indexingPost(['status' => 'draft']);
    $this->get('/admin/posts/'.$draft->id.'/preview')->assertRedirect('/login');
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/posts/'.$draft->id.'/preview')
        ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('content="noindex,nofollow"', false);
});

test('explicit SEO title metadata fallback and JSON LD describe the visible article', function () {
    $author = Author::create(['name' => 'Dina', 'slug' => 'dina']);
    $post = indexingPost(['seo_title' => 'Judul editorial lengkap tanpa suffix', 'author_id' => $author->id, 'featured_image' => '/uploads/cover.webp', 'updated_at' => now()->subHour()]);
    $html = $this->get('/artikel-indeks')->assertOk()->getContent();
    expect($html)->toContain('<title>Judul editorial lengkap tanpa suffix</title>', 'name="twitter:title"', 'https://blog.besofton.id/uploads/cover.webp');
    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
    $schemas = collect($matches[1])->map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR));
    $article = $schemas->firstWhere('@type', 'Article');
    expect($article['author']['url'])->toBe('https://blog.besofton.id/author/dina');
    expect($article['headline'])->toBe($post->title);
    expect($article['dateModified'])->toBe($post->updated_at->toAtomString());
    expect($article['datePublished'])->toBe($post->published_at->toAtomString());
    expect($article['image'])->toBe('https://blog.besofton.id/uploads/cover.webp');
    expect($html)->toContain('name="description" content="'.$article['description'].'"', 'property="og:description" content="'.$article['description'].'"');
});

test('archive pagination has self canonical and ordinary HTML next links', function () {
    $topic = Topic::create(['name' => 'Banyak', 'slug' => 'banyak']);
    foreach (range(1, 14) as $number) {
        indexingPost(['slug' => 'pagination-'.$number])->topics()->attach($topic);
    }
    $this->get('/topik/banyak')->assertOk()->assertSee('href="https://blog.besofton.id/topik/banyak?page=2"', false);
    $this->get('/topik/banyak?page=2')->assertOk()->assertSee('rel="canonical" href="https://blog.besofton.id/topik/banyak?page=2"', false);
});

test('settings use one bulk query and invalidate edits deletes and caller defaults', function () {
    Cache::memo()->forget('settings:values');
    DB::enableQueryLog();
    Setting::valueFor('site_name');
    Setting::valueFor('tagline');
    Setting::valueFor('home_hero_image');
    $reads = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], '"settings"') && str_starts_with($query['query'], 'select'));
    expect($reads)->toHaveCount(1);
    DB::disableQueryLog();
    expect(Setting::valueFor('missing', 'first'))->toBe('first');
    expect(Setting::valueFor('missing', 'second'))->toBe('second');
    Setting::putValue('site_name', 'Nama baru');
    expect(Setting::valueFor('site_name'))->toBe('Nama baru');
    Setting::find('site_name')->delete();
    expect(Setting::valueFor('site_name', 'Fallback'))->toBe('Fallback');
});

test('image variants preserve originals aspect ratio and alpha with safe fallbacks', function () {
    if (! function_exists('imagewebp')) $this->markTestSkipped('GD WebP unavailable');
    $relative = 'uploads/seo-test-'.bin2hex(random_bytes(8)).'.png';
    $path = public_path($relative);
    File::ensureDirectoryExists(dirname($path));
    $image = imagecreatetruecolor(640, 320);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 30, 60, 90, 90));
    imagepng($image, $path);
    imagedestroy($image);
    $original = hash_file('sha256', $path);
    try {
        expect(PublicImage::localPath('https://other.example/'.$relative))->toBeNull();
        expect(PublicImage::localPath('/uploads/../.env'))->toBeNull();
        app(PublicImage::class)->optimize($path);
        $variant = public_path(PublicImage::variant($path, 320));
        expect(is_file($variant))->toBeTrue();
        expect(getimagesize($variant)[0])->toBe(320);
        expect(getimagesize($variant)[1])->toBe(160);
        $copy = imagecreatefromwebp($variant);
        expect(imagecolorsforindex($copy, imagecolorat($copy, 0, 0))['alpha'])->toBeGreaterThan(0);
        imagedestroy($copy);
        expect(hash_file('sha256', $path))->toBe($original);
        expect((string) PublicImage::sources('/'.$relative, '100vw'))->toContain('srcset=', '320w');
        expect((string) PublicImage::sources('https://other.example/image.jpg', '100vw'))->toBe('src="https://other.example/image.jpg"');
    } finally {
        foreach (PublicImage::WIDTHS as $width) File::delete(public_path(PublicImage::variant($path, $width)));
        File::delete($path);
    }
});
