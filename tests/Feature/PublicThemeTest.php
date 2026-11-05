<?php

use App\Livewire\PublicThemeEditor;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Page;
use App\Models\Permission;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use App\Support\PublicTheme;
use Database\Seeders\PublicThemeSettingsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(fn () => PublicTheme::forget());

test('Classic defaults resolve without settings and every emitted color is safe', function () {
    expect(PublicTheme::resolve())->toBe(PublicTheme::defaults());
    expect(PublicTheme::resolve()['background'])->toBe('#f7f3eb');
    expect(PublicTheme::style())->toContain('--public-primary: #e8af4b;', '--public-header-bg-fbf9f5f5: #fbf9f5f5;');
    expect(PublicTheme::style(['text' => 'red; background:url(evil)']))->not->toContain('url(', 'red;');
});

test('admin can open theme settings and CMS body does not receive public variables', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $html = $this->get(route('admin.settings.edit'))->assertOk()->assertSee('Tampilan Website')->assertSee('Preview Tema')->assertSee('Simpan Tema')->getContent();
    preg_match('/<body[^>]*>/', $html, $body);
    expect($body[0])->toContain('admin-app')->not->toContain('public-app', '--public-');
    $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('--public-', false);
});

test('authorized admin saves normalized colors and subsequent public request sees them', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    PublicTheme::resolve();
    Livewire::test(PublicThemeEditor::class)->set('colors.primary', '#2563EB')->call('save')->assertHasNoErrors()->assertDispatched('public-theme-saved');
    expect(Setting::valueFor(PublicTheme::key('primary')))->toBe('#2563eb');
    expect(PublicTheme::resolve()['primary'])->toBe('#2563eb');
    $this->get(route('home'))->assertOk()->assertSee('--public-primary: #2563eb;', false);
});

test('settings manager without superadmin can save while view-only user cannot mount editor', function () {
    $role = Role::create(['name' => 'theme_manager', 'display_name' => 'Theme Manager', 'is_system' => false]);
    $role->permissions()->sync(Permission::whereIn('name', ['cms.access', 'settings.view', 'settings.manage'])->pluck('id'));
    $this->actingAs(User::factory()->create(['role' => 'theme_manager']));
    Livewire::test(PublicThemeEditor::class)->set('colors.primary', '#123456')->call('save')->assertHasNoErrors();
    $role->permissions()->sync(Permission::whereIn('name', ['cms.access', 'settings.view'])->pluck('id'));
    $this->actingAs(User::factory()->create(['role' => 'theme_manager']));
    $this->get(route('admin.settings.edit'))->assertOk()->assertDontSee('data-theme-form', false);
    Livewire::test(PublicThemeEditor::class)->assertForbidden();
});

test('unauthorized roles cannot mount editor', function ($role) {
    $this->actingAs(User::factory()->create(['role' => $role]));
    Livewire::test(PublicThemeEditor::class)->assertForbidden();
    expect(Setting::where('key', 'like', 'theme_%')->count())->toBe(0);
})->with(['reader', 'editor', 'author']);

test('permission is checked again on save after permission revocation', function () {
    $role = Role::create(['name' => 'revocable_theme', 'display_name' => 'Theme', 'is_system' => false]);
    $role->permissions()->sync(Permission::whereIn('name', ['cms.access', 'settings.view', 'settings.manage'])->pluck('id'));
    $user = User::factory()->create(['role' => 'revocable_theme']);
    $this->actingAs($user);
    $editor = Livewire::test(PublicThemeEditor::class)->set('colors.primary', '#123456');
    $role->permissions()->detach();
    $user->unsetRelations();
    $editor->call('save')->assertForbidden();
});

test('invalid HEX and CSS injection cannot be saved', function ($value) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Livewire::test(PublicThemeEditor::class)->set('colors.primary', $value)->call('save')->assertHasErrors('colors.primary');
    expect(Setting::whereKey(PublicTheme::key('primary'))->exists())->toBeFalse();
})->with(['#abc', '#12345g', 'red', 'red; background:url(evil)', 'url(javascript:alert(1))', 'expression(alert(1))', 'var(--unsafe)', '</style><script>alert(1)</script>', '#12345678']);

test('missing and unexpected fields cannot create arbitrary settings', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Livewire::test(PublicThemeEditor::class)->set('colors', ['primary' => '#123456', 'site_name' => '#ffffff'])->call('save')->assertHasErrors(['colors', 'colors.text']);
    expect(Setting::whereKey('site_name')->exists())->toBeFalse();
});

test('unreadable text combinations are blocked on backend while AA warnings remain reviewable', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Livewire::test(PublicThemeEditor::class)->set('colors.text', '#ffffff')->set('colors.background', '#ffffff')->call('save')->assertHasErrors('colors.text');
    expect(Setting::where('key', 'like', 'theme_%')->count())->toBe(0);
    Livewire::test(PublicThemeEditor::class)->set('colors', PublicTheme::defaults())->call('save')->assertHasNoErrors();
});

test('theme cache invalidates for Setting API and direct model updates and deletes', function () {
    PublicTheme::resolve();
    expect(Cache::has(PublicTheme::CACHE_KEY))->toBeTrue();
    Setting::putValue(PublicTheme::key('primary'), '#123456');
    expect(Cache::has(PublicTheme::CACHE_KEY))->toBeFalse();
    expect(PublicTheme::resolve()['primary'])->toBe('#123456');
    Setting::findOrFail(PublicTheme::key('primary'))->update(['value' => '#654321']);
    expect(PublicTheme::resolve()['primary'])->toBe('#654321');
    Setting::findOrFail(PublicTheme::key('primary'))->delete();
    expect(PublicTheme::resolve()['primary'])->toBe(PublicTheme::defaults()['primary']);
});

test('invalid legacy colors log a warning and fall back without CSS output', function () {
    Setting::putValue(PublicTheme::key('primary'), '</style><script>bad</script>');
    Log::shouldReceive('warning')->once()->with('Invalid public theme color; using Classic fallback.', ['key' => PublicTheme::key('primary')]);
    expect(PublicTheme::resolve()['primary'])->toBe(PublicTheme::defaults()['primary']);
    expect(PublicTheme::style())->not->toContain('<script>');
});

test('individual reset and resetting all form colors leave non-theme settings untouched', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    foreach (['site_name' => 'Production Name', 'logo_path' => 'uploads/branding/existing.png', 'favicon_url' => 'https://example.test/icon.png', 'home_hero_heading_line_1' => 'IDE CUSTOM', 'default_description' => 'SEO original'] as $key => $value) {
        Setting::putValue($key, $value);
    }
    $other = Setting::where('key', 'not like', 'theme_%')->pluck('value', 'key')->all();
    PublicTheme::save(array_replace(PublicTheme::defaults(), ['primary' => '#123456', 'accent' => '#654321']));
    $editor = Livewire::test(PublicThemeEditor::class);
    $editor->set('colors.primary', PublicTheme::defaults()['primary'])->call('save')->assertHasNoErrors();
    expect(PublicTheme::resolve()['accent'])->toBe('#654321');
    $editor->set('colors', PublicTheme::defaults())->call('save')->assertHasNoErrors();
    expect(PublicTheme::resolve())->toBe(PublicTheme::defaults());
    expect(Setting::where('key', 'not like', 'theme_%')->pluck('value', 'key')->all())->toBe($other);
});

test('unsaved form edits and presets do not change the active theme', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Livewire::test(PublicThemeEditor::class)->set('colors', PublicTheme::presets()['Modern Blue'])->assertNotDispatched('public-theme-saved');
    expect(PublicTheme::resolve())->toBe(PublicTheme::defaults());
    expect(Setting::where('key', 'like', 'theme_%')->count())->toBe(0);
});

test('seeder is idempotent and preserves production custom theme on deployment', function () {
    $this->seed(PublicThemeSettingsSeeder::class);
    Setting::putValue(PublicTheme::key('primary'), '#123456');
    $this->seed(PublicThemeSettingsSeeder::class);
    $this->seed(PublicThemeSettingsSeeder::class);
    expect(Setting::where('key', 'like', 'theme_%')->count())->toBe(count(PublicTheme::defaults()));
    expect(PublicTheme::resolve()['primary'])->toBe('#123456');
});

test('all public page families share runtime variables header footer and theme meta', function () {
    $category = Category::create(['name' => 'Theme Category', 'slug' => 'theme-category', 'is_active' => true]);
    $tag = Tag::create(['name' => 'Theme Tag', 'slug' => 'theme-tag']);
    $topic = Topic::create(['name' => 'Theme Topic', 'slug' => 'theme-topic']);
    $post = Post::create(['title' => 'Theme Article', 'slug' => 'theme-article', 'status' => 'published', 'published_at' => now()->subHour(), 'category_id' => $category->id, 'content' => '<h2>Content heading</h2><p>Original article body</p>']);
    $video = Video::create(['title' => 'Theme Video', 'slug' => 'theme-video', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->subHour()]);
    $path = LearningPath::create(['title' => 'Theme Path', 'slug' => 'theme-path', 'status' => 'published']);
    $course = Course::create(['title' => 'Theme Course', 'slug' => 'theme-course', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Theme Module']);
    $lesson = $module->lessons()->create(['title' => 'Theme Lesson', 'type' => 'custom', 'content' => '<p>Theme lesson content</p>']);
    $quiz = Quiz::create(['title' => 'Theme Quiz', 'slug' => 'theme-quiz', 'status' => 'published', 'passing_score' => 70]);
    Page::create(['title' => 'About Theme', 'slug' => 'about', 'content' => '<p>About</p>', 'is_published' => true]);
    Setting::putValue(PublicTheme::key('primary'), '#123456');
    Setting::putValue(PublicTheme::key('header-bg'), '#abcdef');
    foreach (['/', '/theme-article', '/kategori', '/kategori/theme-category', '/tag/theme-tag', '/topik', '/topik/theme-topic', '/cari?q=Theme', '/video', '/video/theme-video', '/belajar', '/belajar/theme-path', '/kelas', '/kelas/theme-course', '/kuis/theme-quiz', '/about', '/login', '/register', '/forgot-password'] as $url) {
        $this->get($url)->assertOk()->assertSee('public-body public-app', false)->assertSee('--public-primary: #123456;', false)->assertSee('<meta name="theme-color" content="#abcdef">', false)->assertSee('class="public-site-header"', false)->assertSee('class="public-site-footer"', false);
    }
    $this->actingAs(User::factory()->create(['role' => 'reader']))->get('/akun')->assertOk()->assertSee('--public-primary: #123456;', false);
    $this->get(route('course.lesson', [$course, $lesson]))->assertOk()->assertSee('--public-primary: #123456;', false)->assertSee('Theme lesson content');
    $this->get('/theme-article')->assertOk()->assertSee('Original article body')->assertSee('Content heading');
});

test('all configured tokens and shades are consumed by the public stylesheet', function () {
    $css = file_get_contents(resource_path('css/public.css'));
    foreach (PublicTheme::defaults() as $token => $default) {
        expect($css)->toContain('var(--public-'.$token.')');
    }
    foreach (config('public_theme_shades') as $name => [$token, $original]) {
        expect(PublicTheme::shade(PublicTheme::defaults()[$token], PublicTheme::defaults()[$token], $original))->toBe($original);
        expect($css)->toContain('var(--public-'.$name.')');
    }
});
