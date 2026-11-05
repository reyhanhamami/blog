<?php

use App\Livewire\PublicThemeEditor;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicTheme;
use App\Support\PublicThemeDefaults;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\PublicThemeSettingsSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

beforeEach(fn () => PublicTheme::forget());

dataset('malformed public theme groups', function () {
    yield 'null' => [null];
    yield 'empty array' => [[]];
    yield 'scalar' => ['invalid'];
    yield 'boolean' => [false];
    yield 'null group' => [['Typography' => null]];
    yield 'scalar group' => [['Typography' => 'invalid']];
    yield 'empty group' => [['Typography' => []]];
    yield 'unnamed group' => [[PublicThemeDefaults::GROUPS['Warna Utama']]];
    yield 'scalar field' => [['Typography' => ['text' => '#000000']]];
    $groups = PublicThemeDefaults::GROUPS;
    unset($groups['Typography']['text']);
    yield 'missing required token' => [$groups];
    $groups = PublicThemeDefaults::GROUPS;
    $groups['Typography']['unknown'] = ['Unknown', '#123456', 'Help'];
    yield 'unknown token' => [$groups];
    $groups = PublicThemeDefaults::GROUPS;
    $groups['Typography']['primary'] = $groups['Warna Utama']['primary'];
    yield 'duplicate token' => [$groups];
    foreach ([
        'missing tuple members' => ['Text'],
        'extra tuple member' => ['Text', '#000000', 'Help', 'Extra'],
        'associative tuple' => ['label' => 'Text', 'color' => '#000000', 'help' => 'Help'],
        'empty label' => ['', '#000000', 'Help'],
        'invalid default color' => ['Text', 'red; background:url(evil)', 'Help'],
        'non-string default' => ['Text', 123456, 'Help'],
        'invalid help' => ['Text', '#000000', null],
    ] as $name => $field) {
        $groups = PublicThemeDefaults::GROUPS;
        $groups['Typography']['text'] = $field;
        yield $name => [$groups];
    }
});

test('normal theme config and bundled fallback contain the same complete Classic palette', function () {
    Log::spy();
    expect(PublicTheme::groups())->toBe(PublicThemeDefaults::GROUPS);
    expect(PublicTheme::shades())->toBe(PublicThemeDefaults::SHADES);
    expect(PublicTheme::defaults())->toHaveCount(26)
        ->toMatchArray(['primary' => '#e8af4b', 'background' => '#f7f3eb', 'header-bg' => '#fbf9f5']);
    foreach (PublicTheme::defaults() as $color) {
        expect(PublicTheme::valid($color))->toBeTrue();
    }
    Log::shouldNotHaveReceived('error');
});

test('invalid group structure logs its failure and serves Classic without changing persisted colors', function ($groups) {
    Setting::putValue('theme_primary_color', '#2563eb');
    Setting::putValue('site_name', 'Existing Website');
    $before = Setting::query()->orderBy('key')->get()->toArray();
    Log::spy();
    config(['public_theme.groups' => $groups]);

    expect(PublicTheme::groups())->toBe(PublicThemeDefaults::GROUPS);
    $this->get('/')->assertOk()
        ->assertSee('--public-primary: #2563eb;', false)
        ->assertSee('--public-background: #f7f3eb;', false)
        ->assertSee('--public-header-bg-fbf9f5f5: #fbf9f5f5;', false);
    expect(Setting::query()->orderBy('key')->get()->toArray())->toBe($before);
    Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => str_contains($message, 'using bundled Besofton Classic')
        && $context['config_key'] === 'public_theme.groups'
        && is_string($context['reason']) && $context['reason'] !== ''
        && isset($context['configuration_cached'], $context['action'])
    );
})->with('malformed public theme groups');

test('missing configuration files in an old config repository leave public and editor pages available', function () {
    config(['public_theme' => null, 'public_theme_shades' => null]);
    Log::spy();
    $this->get('/')->assertOk()->assertSee('--public-primary: #e8af4b;', false);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    Livewire::test(PublicThemeEditor::class)->assertSee('Warna Utama')->assertSee('Primary Color')->assertSee('Preview Tema');
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
    expect(PublicTheme::shades())->toBe(PublicThemeDefaults::SHADES);
    Log::shouldHaveReceived('error')->twice();
});

test('missing groups are reported even when database theme values are already cached', function () {
    Setting::putValue('theme_primary_color', '#123456');
    PublicTheme::resolve();
    config(['public_theme' => []]);
    Log::spy();
    expect(PublicTheme::resolve()['primary'])->toBe('#123456');
    Log::shouldHaveReceived('error')->once();
});

test('a valid custom config palette remains supported and normalizes HEX defaults', function () {
    config(['public_theme.groups.Warna Utama.primary' => ['Primary Color', '#123ABC', 'Custom configuration default.']]);
    Log::spy();
    expect(PublicTheme::defaults()['primary'])->toBe('#123abc');
    expect(PublicTheme::resolve()['primary'])->toBe('#123abc');
    Log::shouldNotHaveReceived('error');
});

test('invalid shade structure uses a complete validated fallback and never emits arbitrary CSS', function ($shades) {
    config(['public_theme_shades' => $shades]);
    Log::spy();
    $style = PublicTheme::style(PublicTheme::defaults());
    expect(PublicTheme::shades())->toBe(PublicThemeDefaults::SHADES);
    expect($style)->toContain('--public-header-bg-fbf9f5f5: #fbf9f5f5;')->not->toContain('url(', 'unsafe', '<style>');
    Log::shouldHaveReceived('error')->once()->withArgs(fn ($message, $context) => $context['config_key'] === 'public_theme_shades');
})->with([
    'null' => [null],
    'empty' => [[]],
    'scalar' => ['invalid'],
    'missing shades' => [['primary-9c620b' => ['primary', '#9c620b']]],
    'bad tuple' => [['primary-9c620b' => null]],
    'unknown parent' => [['primary-9c620b' => ['unsafe', '#ffffff']]],
    'color injection' => [['primary-9c620b' => ['primary', '#ffffff; background:url(unsafe)']]],
    'variable name injection' => [['unsafe; color' => ['primary', '#ffffff']]],
]);

test('theme seeding with unavailable config fills only missing keys and remains idempotent', function ($groups) {
    Setting::putValue('theme_primary_color', '#123456');
    Setting::putValue('theme_text_color', '#654321');
    Setting::putValue('site_name', 'Production Website');
    $before = Setting::query()->orderBy('key')->get()->toArray();
    config(['public_theme.groups' => $groups, 'public_theme_shades' => null]);
    Log::spy();
    $this->seed(PublicThemeSettingsSeeder::class);
    config(['public_theme.groups' => $groups]);
    $this->seed(PublicThemeSettingsSeeder::class);
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(26);
    expect(Setting::query()->whereIn('key', array_column($before, 'key'))->orderBy('key')->get()->toArray())->toBe($before);
    expect(PublicTheme::resolve()['primary'])->toBe('#123456');
})->with(['missing' => [null], 'empty' => [[]], 'malformed' => [['Broken' => null]]]);

test('production DatabaseSeeder calls the theme seeder without demo content or overwriting custom colors', function () {
    config(['public_theme' => null]);
    Log::spy();
    Setting::putValue('theme_primary_color', '#123456');
    $demo = Mockery::mock(DemoSeeder::class);
    $demo->shouldNotReceive('__invoke');
    app()->instance(DemoSeeder::class, $demo);
    $this->seed(DatabaseSeeder::class);
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(26);
    expect(Setting::valueFor('theme_primary_color'))->toBe('#123456');
    expect(Post::count())->toBe(0);
});

test('cached configuration boots with valid normal stale and malformed theme definitions', function () {
    $relativeCachePath = 'storage/framework/testing/theme-config-'.Str::uuid().'.php';
    $cachePath = base_path($relativeCachePath);
    File::ensureDirectoryExists(dirname($cachePath));
    $environment = [
        'APP_CONFIG_CACHE' => $relativeCachePath,
        'APP_ENV' => 'testing',
        'APP_KEY' => config('app.key'),
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'LOG_CHANNEL' => 'stderr',
    ];
    try {
        (new Process([PHP_BINARY, 'artisan', 'config:cache', '--no-ansi'], base_path(), $environment))->setTimeout(30)->mustRun();
        $cached = require $cachePath;
        expect($cached['public_theme']['groups'])->toBe(PublicThemeDefaults::GROUPS);
        expect($cached['public_theme_shades'])->toBe(PublicThemeDefaults::SHADES);
        $script = <<<'PHP'
        require 'vendor/autoload.php';
        $app = require 'bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $colors = App\Support\PublicTheme::defaults();
        echo json_encode(['cached' => $app->configurationIsCached(), 'loaded' => $app['config_loaded_from_cache'], 'colors' => $colors, 'style' => App\Support\PublicTheme::style($colors)]);
        PHP;
        $variants = ['normal' => $cached, 'missing' => $cached, 'malformed' => $cached];
        unset($variants['missing']['public_theme'], $variants['missing']['public_theme_shades']);
        $variants['malformed']['public_theme']['groups'] = ['Broken' => null];
        $variants['malformed']['public_theme_shades'] = ['unsafe' => 'invalid'];
        foreach ($variants as $name => $configuration) {
            File::put($cachePath, '<?php return '.var_export($configuration, true).';');
            $process = (new Process([PHP_BINARY, '-r', $script], base_path(), $environment))->setTimeout(30)->mustRun();
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            expect($result['cached'])->toBeTrue();
            expect($result['loaded'])->toBeTrue();
            expect($result['colors'])->toBe(PublicTheme::defaults());
            expect($result['style'])->toContain('--public-primary: #e8af4b;', '--public-header-bg-fbf9f5f5: #fbf9f5f5;');
            expect(require $cachePath)->toBe($configuration);
            if ($name !== 'normal') {
                expect($process->getErrorOutput())->toContain('Invalid public theme configuration', 'public_theme.groups', 'public_theme_shades');
            }
        }
    } finally {
        File::delete($cachePath);
    }
});

test('cPanel tasks clear stale configuration before seeding and rebuild caches after migration', function () {
    $tasks = Yaml::parseFile(base_path('.cpanel.yml'))['deployment']['tasks'];
    expect($tasks[0])->toBe('set -e');
    $commands = array_values(array_filter($tasks, fn ($task) => str_contains($task, '/artisan"')));
    $actions = array_map(fn ($task) => explode('/artisan" ', $task, 2)[1], $commands);
    expect($actions)->toBe(['config:clear', 'migrate --force --seed', 'optimize:clear', 'config:cache', 'view:cache']);
    expect(implode("\n", $tasks))->not->toContain('migrate:fresh', 'db:wipe', 'DemoSeeder');
});
