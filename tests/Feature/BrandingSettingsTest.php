<?php

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    foreach (['site_name', 'logo_url', 'favicon_url', 'logo_path', 'favicon_path'] as $key) {
        Cache::forget('setting:'.$key);
    }
});

afterEach(function () {
    foreach (['logo_path', 'favicon_path'] as $key) {
        Branding::deleteOldUpload(Setting::query()->whereKey($key)->value('value'));
    }
});

function brandingSettingsData(array $extra = []): array
{
    return array_merge(['site_name' => 'Besofton Insights', 'tagline' => '', 'logo_url' => '', 'favicon_url' => '', 'default_description' => '', 'google_verification' => ''], $extra);
}

test('admin settings and layout use the admin asset entry', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->get(route('admin.settings.edit'))->assertOk()->assertSee('data-branding-upload="logo"', false)->getContent();
    expect($html)->toMatch('~(?:resources/css/admin\.css|/build/assets/admin-[^" ]+\.css)~');
    expect($html)->not->toContain('resources/css/public.css', '/build/assets/public-');
    $this->get(route('admin.dashboard'))->assertOk()->assertSee('admin-shell')->assertSee('admin-main');
    $this->get(route('admin.posts.index'))->assertOk()->assertSee('admin-app');
    $this->get('/admin/articles')->assertRedirect(route('admin.posts.index'));
    $this->get('/admin/articles/create')->assertRedirect(route('admin.posts.create'));
    $this->get(route('admin.users.index'))->assertOk()->assertSee('admin-app');
    $this->get(route('home'))->assertOk()->assertSee('public-body')->assertDontSee('admin-app');
});

test('unauthorized user cannot update branding', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']))
        ->patch(route('admin.settings.update'), brandingSettingsData(['logo' => UploadedFile::fake()->image('logo.png', 120, 120)]))->assertForbidden();
    expect(Setting::query()->whereKey('logo_path')->exists())->toBeFalse();
});

test('valid logo formats are stored and shown on the public page', function (string $filename) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->patch(route('admin.settings.update'), brandingSettingsData(['logo' => UploadedFile::fake()->image($filename, 120, 120)]))->assertRedirect();
    $path = Setting::valueFor('logo_path');
    expect($path)->toStartWith('uploads/branding/logo-');
    expect(File::exists(public_path($path)))->toBeTrue();
    $this->get(route('home'))->assertOk()->assertSee(asset($path), false);
    $this->get(route('admin.settings.edit'))->assertOk()->assertSee(asset($path), false);
})->with(['logo.png', 'logo.jpg', 'logo.webp']);

test('valid favicon is stored and appears in the public head', function (string $filename) {
    $file = $filename === 'favicon.ico'
        ? UploadedFile::fake()->createWithContent($filename, file_get_contents(public_path('favicon.ico')))
        : UploadedFile::fake()->image($filename, 64, 64);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->patch(route('admin.settings.update'), brandingSettingsData(['favicon' => $file]))->assertRedirect();
    $path = Setting::valueFor('favicon_path');
    expect(File::exists(public_path($path)))->toBeTrue();
    $this->get(route('home'))->assertOk()->assertSee('<link rel="icon" href="'.asset($path).'">', false);
})->with(['favicon.png', 'favicon.ico']);

test('invalid and disguised images are rejected', function (string $field, UploadedFile $file) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->patch(route('admin.settings.update'), brandingSettingsData([$field => $file]))->assertSessionHasErrors($field);
    expect(Setting::query()->whereKey($field.'_path')->exists())->toBeFalse();
})->with([
    ['logo', UploadedFile::fake()->createWithContent('payload.php', '<?php echo 1;')],
    ['logo', UploadedFile::fake()->createWithContent('image.jpg', '<html>not an image</html>')],
    ['logo', UploadedFile::fake()->image('oversize.png', 120, 120)->size(4097)],
    ['favicon', UploadedFile::fake()->createWithContent('fake.ico', '<html>not an icon</html>')],
]);

test('saving without files preserves branding and invalidates settings cache', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->patch(route('admin.settings.update'), brandingSettingsData(['logo' => UploadedFile::fake()->image('logo.png', 120, 120)]))->assertRedirect();
    $path = Setting::valueFor('logo_path');
    expect(Setting::valueFor('site_name'))->toBe('Besofton Insights');
    $this->patch(route('admin.settings.update'), brandingSettingsData(['site_name' => 'New name']))->assertRedirect();
    expect(Setting::valueFor('logo_path'))->toBe($path);
    expect(Setting::valueFor('site_name'))->toBe('New name');
    expect(File::exists(public_path($path)))->toBeTrue();
});

test('replacing branding never deletes a default or external asset', function () {
    $default = public_path('favicon.ico');
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))
        ->patch(route('admin.settings.update'), brandingSettingsData(['logo_url' => 'https://example.test/brand.png', 'logo' => UploadedFile::fake()->image('logo.png', 120, 120)]))->assertRedirect();
    $old = Setting::valueFor('logo_path');
    $this->patch(route('admin.settings.update'), brandingSettingsData(['logo_url' => 'https://example.test/brand.png', 'logo' => UploadedFile::fake()->image('replacement.png', 120, 120)]))->assertRedirect();
    expect(File::exists(public_path($old)))->toBeFalse();
    expect(File::exists($default))->toBeTrue();
    expect(Setting::valueFor('logo_url'))->toBe('https://example.test/brand.png');
});
