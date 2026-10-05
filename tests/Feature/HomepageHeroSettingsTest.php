<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Topic;
use App\Models\User;
use App\Support\HomeHeroSettings;
use Database\Seeders\HomepageSettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    foreach (array_keys(HomeHeroSettings::DEFAULTS) as $key) {
        Cache::forget('setting:'.$key);
    }
});

function homepageSettingsInput(array $extra = []): array
{
    return array_merge([
        'site_name' => 'Besofton Insights', 'tagline' => 'Site tagline',
        'logo_url' => '', 'favicon_url' => '', 'default_description' => '', 'google_verification' => '',
    ], $extra);
}

function heroMarkup(string $html): string
{
    return explode('</section>', explode('<section class="home-hero">', $html, 2)[1], 2)[0];
}

test('homepage uses independent defaults and branded image fallback without settings or posts', function () {
    $html = heroMarkup($this->get(route('home'))->assertOk()->getContent());

    expect($html)->toContain('BESOFTON INSIGHTS', 'IDE.<br>PANDUAN.<br><em>INSIGHTS.</em>', 'Build<br>Grow<br>', 'Insight untuk Pertumbuhan Digital', 'AI for Developers', 'Laravel Development', 'Cari topik atau artikel...');
    expect($html)->not->toContain('<img src=');
});

test('homepage escapes custom hero wording and separates notes from post topics', function () {
    Setting::putValue('home_hero_eyebrow', 'IDE BARU');
    Setting::putValue('home_hero_heading_line_1', 'BELAJAR.');
    Setting::putValue('home_hero_heading_line_2', 'BERTUMBUH.');
    Setting::putValue('home_hero_heading_highlight', 'BERSAMA.');
    Setting::putValue('home_hero_description', 'Deskripsi khusus.');
    Setting::putValue('home_hero_gold_note', 'Catatan emas khusus');
    Setting::putValue('home_hero_black_label', "SATU\nDUA");
    Setting::putValue('home_hero_white_notes', "Pertama\n<script>alert(1)</script>");
    Setting::putValue('home_hero_search_placeholder', 'Temukan ide...');
    $post = Post::create(['title' => 'Post berbeda', 'slug' => 'post-berbeda', 'status' => 'published', 'published_at' => now()->subDay(), 'content_type' => 'article', 'is_featured' => true]);
    $topic = Topic::create(['name' => 'Topik dari Post', 'slug' => 'topik-dari-post']);
    $post->topics()->attach($topic);

    $html = heroMarkup($this->get(route('home'))->assertOk()->getContent());
    expect($html)->toContain('IDE BARU', 'BELAJAR.<br>BERTUMBUH.<br><em>BERSAMA.</em>', 'Deskripsi khusus.', 'Catatan emas khusus', 'SATU<br>DUA', 'Pertama', '&lt;script&gt;alert(1)&lt;/script&gt;', 'Temukan ide...');
    expect($html)->not->toContain('Topik dari Post', '<script>alert(1)</script>');
});

test('CMS image wins over featured post and clearing only removes the hero reference', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin);
    $upload = $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('hero.png', 1200, 800), 'alt_text' => 'Gambar hero CMS']);
    $upload->assertCreated();
    $path = Media::firstOrFail()->path;
    $imageUrl = $upload->json('url');
    try {
        $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('fake.png', '<html>not an image</html>'), 'alt_text' => 'Invalid'])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_image' => $imageUrl, 'home_hero_image_alt' => 'Gambar hero CMS']))->assertRedirect()->assertSessionHas('success', 'Pengaturan homepage berhasil diperbarui.');
        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('data-home-hero-picker', false)->assertSee('data-media-gallery', false)->assertSee($imageUrl);
        Post::create(['title' => 'Featured lain', 'slug' => 'featured-lain', 'status' => 'published', 'published_at' => now()->subDay(), 'content_type' => 'article', 'is_featured' => true, 'featured_image' => 'https://example.test/post-cover.jpg']);
        $html = heroMarkup($this->get(route('home'))->assertOk()->getContent());
        expect($html)->toContain($imageUrl, 'alt="Gambar hero CMS"')->not->toContain('post-cover.jpg');
        $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_heading_line_1' => 'BARU.']))->assertRedirect();
        expect(Setting::valueFor('home_hero_image'))->toBe($imageUrl);
        $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_image' => '']))->assertRedirect();
        expect(heroMarkup($this->get(route('home'))->assertOk()->getContent()))->toContain('Build<br>Grow<br>');
        expect(File::exists(public_path($path)))->toBeTrue();
    } finally {
        File::delete(public_path($path));
    }
});

test('homepage settings validate inputs and show saved changes immediately', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_image' => 'javascript:alert(1)']))->assertSessionHasErrors('home_hero_image');
    $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_image' => 'https://example.test/hero.webp']))->assertRedirect();
    expect(heroMarkup($this->get(route('home'))->assertOk()->getContent()))->toContain('https://example.test/hero.webp', 'alt="Besofton Insights"');
    $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_white_notes' => "one\ntwo\nthree\nfour\nfive"]))->assertSessionHasErrors('home_hero_white_notes');
    $this->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_heading_line_1' => 'CMS BARU.', 'home_hero_white_notes' => "Satu\n\nDua"]))->assertRedirect();
    expect(Setting::valueFor('home_hero_white_notes'))->toBe("Satu\nDua");
    $this->get(route('home'))->assertOk()->assertSee('CMS BARU.')->assertSee('Satu')->assertSee('Dua');
    $this->actingAs(User::factory()->create(['role' => 'editor']))->patch(route('admin.settings.update'), homepageSettingsInput(['home_hero_heading_line_1' => 'DITOLAK']))->assertForbidden();
    expect(Setting::valueFor('home_hero_heading_line_1'))->toBe('CMS BARU.');
});

test('homepage defaults seeder is idempotent and preserves CMS edits', function () {
    $this->seed(HomepageSettingsSeeder::class);
    $this->seed(HomepageSettingsSeeder::class);
    expect(Setting::query()->where('key', 'like', 'home_hero_%')->count())->toBe(count(HomeHeroSettings::DEFAULTS));
    Setting::putValue('home_hero_description', 'Deskripsi milik admin');
    $this->seed(HomepageSettingsSeeder::class);
    expect(Setting::valueFor('home_hero_description'))->toBe('Deskripsi milik admin');
    expect(Setting::query()->where('key', 'like', 'home_hero_%')->count())->toBe(count(HomeHeroSettings::DEFAULTS));
});
