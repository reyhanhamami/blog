<?php

use App\Models\Category;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\Video;
use Illuminate\Support\Facades\Cache;

function discoveryPost(string $slug, Category $category, array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Artikel '.$slug,
        'slug' => $slug,
        'category_id' => $category->id,
        'status' => 'published',
        'published_at' => now()->subDay(),
        'content_type' => 'article',
        'content_plain' => 'Panduan yang dapat dibaca.',
        'views' => 0,
    ], $attributes));
}

test('category is an indexable landing page with its own canonical and crawlable switcher', function () {
    $ai = Category::create(['name' => 'Artificial Intelligence', 'slug' => 'artificial-intelligence', 'description' => 'Penerapan AI yang praktis.', 'is_active' => true]);
    $web = Category::create(['name' => 'Website Development', 'slug' => 'website-development', 'is_active' => true]);
    $draftOnly = Category::create(['name' => 'Kategori Draft', 'slug' => 'kategori-draft', 'is_active' => true]);
    discoveryPost('prompt-ai', $ai);
    discoveryPost('laravel', $web);
    discoveryPost('belum-terbit', $draftOnly, ['status' => 'draft']);

    $html = $this->get(route('category.show', $ai))->assertOk()->getContent();
    expect($html)->toContain('<title>Artificial Intelligence | Besofton Insights</title>')
        ->toContain('<h1>Artificial Intelligence</h1>')
        ->toContain('Penerapan AI yang praktis.')
        ->toContain('<link rel="canonical" href="'.route('category.show', $ai).'">')
        ->toContain('href="'.route('category.show', $web).'"')
        ->toContain('BreadcrumbList')
        ->not->toContain('href="'.route('category.show', $draftOnly).'"');
    $this->assertSame(1, substr_count($html, '<h1>'));

    config()->set('app.env', 'production');
    $this->get(route('category.show', $ai))->assertOk()->assertSee('<meta name="robots" content="index,follow">', false);
    $this->get(route('search', ['q' => 'prompt']))->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false);
});

test('category search and topic and content type filters keep the category constraint', function () {
    $ai = Category::create(['name' => 'AI', 'slug' => 'ai', 'is_active' => true]);
    $web = Category::create(['name' => 'Web', 'slug' => 'web', 'is_active' => true]);
    $topic = Topic::create(['name' => 'AI Developer', 'slug' => 'ai-developer']);
    $match = discoveryPost('prompt-laravel', $ai, ['title' => 'Prompt Laravel Tepat', 'content_type' => 'tutorial']);
    $match->topics()->attach($topic);
    discoveryPost('prompt-lain', $ai, ['title' => 'Prompt Laravel Lain', 'content_type' => 'article']);
    discoveryPost('web-prompt', $web, ['title' => 'Prompt Laravel Web', 'content_type' => 'tutorial']);
    discoveryPost('draft-prompt', $ai, ['title' => 'Prompt Laravel Draft', 'status' => 'draft', 'content_type' => 'tutorial']);

    $url = route('category.show', $ai).'?q=Prompt&topic=ai-developer&type=tutorial';
    $this->get($url)->assertOk()->assertSee('Prompt Laravel Tepat')
        ->assertDontSee('Prompt Laravel Lain')->assertDontSee('Prompt Laravel Web')->assertDontSee('Prompt Laravel Draft')
        ->assertSee('value="Prompt"', false)->assertSee('value="ai-developer" selected', false)
        ->assertSee('value="tutorial" selected', false)->assertSee('discovery-article-grid is-single');

    $this->get(route('search', ['q' => 'Prompt', 'category' => 'ai', 'topic' => 'ai-developer', 'content_type' => 'tutorial', 'type' => 'article']))->assertOk()
        ->assertSee('Prompt Laravel Tepat')->assertDontSee('Prompt Laravel Web')->assertDontSee('Prompt Laravel Lain');
});

test('category sorting uses real dates and views with safe fallback', function () {
    $category = Category::create(['name' => 'Teknologi', 'slug' => 'teknologi', 'is_active' => true]);
    discoveryPost('lama', $category, ['title' => 'Artikel Paling Lama', 'published_at' => now()->subDays(5), 'views' => 90]);
    discoveryPost('baru', $category, ['title' => 'Artikel Paling Baru', 'published_at' => now()->subDay(), 'views' => 2]);
    $base = route('category.show', $category);

    $latest = $this->get($base)->assertOk()->getContent();
    $oldest = $this->get($base.'?sort=oldest')->assertOk()->getContent();
    $popular = $this->get($base.'?sort=popular')->assertOk()->getContent();
    $invalid = $this->get($base.'?sort=published_at;drop')->assertOk()->getContent();

    expect(strpos($latest, 'Artikel Paling Baru'))->toBeLessThan(strpos($latest, 'Artikel Paling Lama'));
    expect(strpos($oldest, 'Artikel Paling Lama'))->toBeLessThan(strpos($oldest, 'Artikel Paling Baru'));
    expect(strpos($popular, 'Artikel Paling Lama'))->toBeLessThan(strpos($popular, 'Artikel Paling Baru'));
    expect(strpos($invalid, 'Artikel Paling Baru'))->toBeLessThan(strpos($invalid, 'Artikel Paling Lama'));
    $this->get($base.'?sort%5B%5D=popular&q%5B%5D=test')->assertOk();
});

test('pagination preserves discovery filters and single result has adaptive layout', function () {
    $category = Category::create(['name' => 'AI', 'slug' => 'ai', 'is_active' => true]);
    for ($index = 1; $index <= 13; $index++) {
        discoveryPost('laravel-'.$index, $category, ['title' => 'Laravel Materi '.$index, 'content_type' => 'tutorial']);
    }

    $html = $this->get(route('category.show', $category).'?q=Laravel&type=tutorial&sort=oldest')->assertOk()
        ->assertSee('13 artikel ditemukan')->getContent();
    preg_match('~href="([^"]*page=2[^"]*)"~', $html, $matches);
    $paginationUrl = html_entity_decode($matches[1] ?? '');
    parse_str((string) parse_url($paginationUrl, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['q' => 'Laravel', 'type' => 'tutorial', 'sort' => 'oldest', 'page' => '2']);

    $this->get($paginationUrl)->assertOk()->assertSee('Laravel Materi 13');
    $this->get(route('category.show', $category).'?q=Materi 13')->assertOk()->assertSee('discovery-article-grid is-single');
});

test('difficulty filters only appear for real values and global extras do not bypass article filters', function () {
    $category = Category::create(['name' => 'AI', 'slug' => 'ai', 'is_active' => true]);
    discoveryPost('prompt-pemula', $category, ['title' => 'Prompt untuk Pemula', 'difficulty' => 'beginner']);
    discoveryPost('prompt-lanjut', $category, ['title' => 'Prompt Lanjutan', 'difficulty' => 'advanced']);
    Video::create(['title' => 'Prompt Video', 'slug' => 'prompt-video', 'status' => 'published', 'youtube_id' => 'abcdefghijk', 'published_at' => now()->subMinute()]);

    $this->get(route('category.show', $category).'?difficulty=beginner')->assertOk()
        ->assertSee('Prompt untuk Pemula')->assertDontSee('Prompt Lanjutan')
        ->assertSee('value="beginner" selected', false)->assertDontSee('value="intermediate"', false);
    $this->get(route('search', ['q' => 'Prompt', 'category' => 'ai']))->assertOk()
        ->assertSee('Prompt untuk Pemula')->assertDontSee('Prompt Video');
    $this->get(route('search', ['q' => 'Prompt']))->assertOk()->assertSee('Prompt Video');
});

test('search video tab paginates published results and preserves the keyword', function () {
    for ($index = 1; $index <= 13; $index++) {
        Video::create(['title' => 'Prompt Video '.$index, 'slug' => 'prompt-video-'.$index, 'status' => 'published', 'youtube_id' => 'abcdefghijk', 'published_at' => now()->subMinutes($index)]);
    }
    Video::create(['title' => 'Prompt Video Draft', 'slug' => 'prompt-video-draft', 'status' => 'draft', 'youtube_id' => 'abcdefghijk']);

    $html = $this->get(route('search', ['q' => 'Prompt', 'type' => 'video']))->assertOk()
        ->assertSee('Prompt Video 1')->assertDontSee('Prompt Video Draft')->getContent();
    $this->assertSame(12, substr_count($html, 'class="public-card public-panel discovery-card"'));
    preg_match('~href="([^"]*page=2[^"]*)"~', $html, $matches);
    $paginationUrl = html_entity_decode($matches[1] ?? '');
    parse_str((string) parse_url($paginationUrl, PHP_URL_QUERY), $query);
    expect($query)->toMatchArray(['q' => 'Prompt', 'type' => 'video', 'page' => '2']);
    $this->get($paginationUrl)->assertOk()->assertSee('Prompt Video 13');
});

test('footer reuses one newsletter form and only shows real menu and configured social links', function () {
    $menu = Menu::create(['location' => 'footer', 'name' => 'Footer']);
    $menu->items()->create(['label' => 'Tentang Besofton', 'url' => '/about', 'is_active' => true, 'sort_order' => 1]);
    Cache::forget('menu:footer');

    $html = $this->get(route('home'))->assertOk()->getContent();
    expect($html)->toContain('Jangan lewatkan', 'Eksplorasi', 'Belajar', 'Tentang Besofton', '© '.date('Y').' Besofton')
        ->not->toContain('aria-label="Instagram Besofton"');
    $this->assertSame(1, substr_count($html, 'id="newsletter-email"'));

    Setting::putValue('instagram_url', 'https://instagram.com/besofton');
    $this->get(route('home'))->assertOk()->assertSee('aria-label="Instagram Besofton"', false)
        ->assertDontSee('aria-label="LinkedIn Besofton"', false);
});
