<?php

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use Database\Seeders\HeaderNavigationSeeder;

function desktopNavigation(string $html): string
{
    preg_match('~<nav class="public-desktop-nav"[^>]*>(.*?)</nav>~s', $html, $matches);

    return $matches[1] ?? '';
}

test('header uses five product destinations with search as a separate action', function () {
    $html = $this->get(route('home'))->assertOk()->getContent();
    $navigation = desktopNavigation($html);

    foreach (['Insights', 'Topik', 'Belajar', 'Kelas', 'Video'] as $label) {
        expect($navigation)->toContain('>'.$label.'</a>');
    }
    expect($navigation)->not->toContain('>Cari</a>');
    expect($html)->toContain('class="public-search-link', 'href="'.route('search').'"', 'public-login-link');
    $this->assertSame(5, substr_count($navigation, '<a '));
    $this->assertSame(1, substr_count($html, 'class="public-search-link'));
});

test('header seeder is idempotent and preserves CMS custom links', function () {
    $menu = Menu::create(['location' => 'header', 'name' => 'Header Menu']);
    $menu->items()->create(['label' => 'Panduan', 'url' => '/panduan', 'sort_order' => 80, 'is_active' => true]);

    $this->seed(HeaderNavigationSeeder::class);
    $this->seed(HeaderNavigationSeeder::class);

    expect($menu->items()->count())->toBe(6);
    expect($menu->items()->where('url', '/panduan')->first()->label)->toBe('Panduan');
    $navigation = desktopNavigation($this->get(route('home'))->assertOk()->getContent());
    expect($navigation)->toContain('>Panduan</a>', 'href="/topik"', 'href="/belajar"', 'href="/kelas"', 'href="/video"');
});

test('active product navigation follows each index and detail route', function () {
    $topic = Topic::create(['name' => 'Pengembangan', 'slug' => 'pengembangan']);
    $path = LearningPath::create(['title' => 'Jalur', 'slug' => 'jalur', 'status' => 'published']);
    $course = Course::create(['title' => 'Kelas', 'slug' => 'kelas', 'status' => 'published']);
    $video = Video::create(['title' => 'Tutorial', 'slug' => 'tutorial', 'status' => 'published', 'youtube_id' => 'abcdefghijk', 'published_at' => now()->subMinute()]);
    $post = Post::create(['title' => 'Artikel', 'slug' => 'artikel', 'status' => 'published', 'published_at' => now()->subMinute(), 'content' => '<p>Isi artikel.</p>']);

    foreach ([
        [route('home'), 'Insights'], [route('post.show', $post->slug), 'Insights'],
        [route('topics.index'), 'Topik'], [route('topic.show', $topic), 'Topik'],
        [route('paths.index'), 'Belajar'], [route('path.show', $path), 'Belajar'],
        [route('courses.index'), 'Kelas'], [route('course.show', $course), 'Kelas'],
        [route('videos.index'), 'Video'], [route('video.show', $video), 'Video'],
    ] as [$url, $label]) {
        $navigation = desktopNavigation($this->get($url)->assertOk()->getContent());
        expect($navigation)->toMatch('~class="is-active"\s+aria-current="page"\s*>'.$label.'</a>~');
        $this->assertSame(1, substr_count($navigation, 'aria-current="page"'));
    }
});

test('new public indexes show available content and exclude drafts and deleted content', function () {
    $visibleTopic = Topic::create(['name' => 'Topik Terbit', 'slug' => 'topik-terbit']);
    $hiddenTopic = Topic::create(['name' => 'Topik Draft', 'slug' => 'topik-draft']);
    $publishedPost = Post::create(['title' => 'Artikel Terbit', 'slug' => 'artikel-terbit', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $draftPost = Post::create(['title' => 'Artikel Draft', 'slug' => 'artikel-draft', 'status' => 'draft']);
    $publishedPost->topics()->attach($visibleTopic);
    $draftPost->topics()->attach($hiddenTopic);

    LearningPath::create(['title' => 'Jalur Terbit', 'slug' => 'jalur-terbit', 'status' => 'published']);
    LearningPath::create(['title' => 'Jalur Draft', 'slug' => 'jalur-draft', 'status' => 'draft']);
    Course::create(['title' => 'Kelas Terbit', 'slug' => 'kelas-terbit', 'status' => 'published']);
    $deletedCourse = Course::create(['title' => 'Kelas Terhapus', 'slug' => 'kelas-terhapus', 'status' => 'published']);
    $deletedCourse->delete();
    Video::create(['title' => 'Video Terbit', 'slug' => 'video-terbit', 'status' => 'published', 'youtube_id' => 'abcdefghijk', 'published_at' => now()->subMinute()]);
    Video::create(['title' => 'Video Terjadwal', 'slug' => 'video-terjadwal', 'status' => 'published', 'youtube_id' => 'abcdefghijk', 'published_at' => now()->addDay()]);

    $this->get(route('topics.index'))->assertOk()->assertSee('Topik Terbit')->assertDontSee('Topik Draft');
    $this->get(route('paths.index'))->assertOk()->assertSee('Jalur Terbit')->assertDontSee('Jalur Draft');
    $this->get(route('courses.index'))->assertOk()->assertSee('Kelas Terbit')->assertDontSee('Kelas Terhapus');
    $this->get(route('videos.index'))->assertOk()->assertSee('Video Terbit')->assertDontSee('Video Terjadwal');
});

test('account menu uses the signed in user and limits CMS access', function () {
    $reader = User::factory()->create(['name' => 'Pembaca QA', 'role' => 'reader']);
    $html = $this->actingAs($reader)->get(route('home'))->assertOk()->getContent();
    expect($html)->toContain('Pembaca QA', 'Bookmark', 'Lanjutkan Belajar', 'id="public-account-menu"')->not->toContain('Masuk ke CMS');

    $editor = User::factory()->create(['name' => 'Editor QA', 'role' => 'editor']);
    $this->actingAs($editor)->get(route('home'))->assertOk()->assertSee('Editor QA')->assertSee('Masuk ke CMS');
});
