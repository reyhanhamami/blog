<?php

use App\Livewire\PublicThemeEditor;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicTheme;
use Database\Seeders\PublicThemeSettingsSeeder;
use Livewire\Livewire;

beforeEach(fn () => PublicTheme::forget());

test('Red White defines all 26 existing tokens and passes every configured AA contrast pair', function () {
    $presets = PublicTheme::presets();
    expect(array_keys($presets))->toBe(['Besofton Classic', 'Modern Blue', 'Forest Green', 'Red White']);
    $red = $presets['Red White'];
    expect($red)->toHaveCount(26)->toMatchArray([
        'primary' => '#c62828', 'primary-hover' => '#a91d1d', 'secondary' => '#8e1b1b',
        'accent' => '#e53935', 'background' => '#fafafa', 'surface' => '#ffffff',
        'heading' => '#171717', 'text' => '#262626', 'text-muted' => '#666666', 'border' => '#e5e5e5',
    ]);
    expect(array_keys($red))->toBe(array_keys(PublicTheme::defaults()));
    foreach ($red as $color) {
        expect(PublicTheme::valid($color))->toBeTrue();
    }
    foreach (PublicTheme::contrastChecks($red) as $check) {
        expect($check['ratio'], $check['label'])->toBeGreaterThanOrEqual($check['target']);
    }
    expect($presets['Besofton Classic'])->toBe(PublicTheme::defaults());
    expect($presets['Modern Blue']['primary'])->toBe('#1d4ed8');
    expect($presets['Forest Green']['primary'])->toBe('#166534');
});

test('Red White previews without persisting and saves without affecting branding or CMS', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Setting::putValue('site_name', 'Existing Site');
    Setting::putValue('logo_url', 'https://example.test/existing-logo.png');
    $red = PublicTheme::presets()['Red White'];
    $editor = Livewire::test(PublicThemeEditor::class)->assertSee('Red White')->set('colors', $red);
    expect(PublicTheme::resolve())->toBe(PublicTheme::defaults());
    $editor->call('save')->assertHasNoErrors()->assertDispatched('public-theme-saved');
    expect(PublicTheme::resolve())->toBe($red);
    $this->seed(PublicThemeSettingsSeeder::class);
    $this->seed(PublicThemeSettingsSeeder::class);
    expect(PublicTheme::resolve())->toBe($red);
    expect(Setting::valueFor('site_name'))->toBe('Existing Site');
    expect(Setting::valueFor('logo_url'))->toBe('https://example.test/existing-logo.png');
    $this->get('/')->assertOk()->assertSee('--public-primary: #c62828;', false);
    $this->get('/admin')->assertOk()->assertSee('admin-app')->assertDontSee('--public-primary', false);
});

test('featured media retains its original URL and accessible image while soft fill is decorative', function () {
    $post = Post::create([
        'title' => 'Featured image fixture', 'slug' => 'featured-image-fixture',
        'featured_image' => 'https://example.test/original-portrait.png',
        'featured_image_alt' => 'Original full portrait', 'is_featured' => true,
        'status' => 'published', 'published_at' => now()->subHour(),
    ]);
    $html = $this->get('/')->assertOk()->getContent();
    expect($html)->toContain('class="featured-image" src="https://example.test/original-portrait.png" alt="Original full portrait"');
    expect($html)->toContain('class="featured-image-fill" src="https://example.test/original-portrait.png" alt="" aria-hidden="true"');
    expect($post->fresh()->featured_image)->toBe('https://example.test/original-portrait.png');
});
