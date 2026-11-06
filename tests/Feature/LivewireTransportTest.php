<?php

use App\Http\Middleware\LogLivewireTransportFailures;
use App\Models\Setting;
use App\Models\User;
use App\Support\PublicTheme;
use Database\Seeders\PublicThemeSettingsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

function themeTransportPayload(string $html, array $updates = []): array
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    foreach ($matches[1] as $encoded) {
        $snapshot = html_entity_decode($encoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (json_decode($snapshot, true)['memo']['name'] === 'public-theme-editor') {
            return ['components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['path' => '', 'method' => 'save', 'params' => []]]]]];
        }
    }
    throw new RuntimeException('Theme snapshot missing.');
}

beforeEach(fn () => PublicTheme::forget());

test('one dynamic Livewire update route accepts POST only and scripts target that route', function () {
    $routes = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route) => str_ends_with($route->getName() ?? '', 'livewire.update'));
    expect($routes)->toHaveCount(1);
    $route = $routes->first();
    expect($route->methods())->toBe(['POST']);
    expect($route->middleware())->toContain('web');
    $uri = app('livewire')->getUpdateUri();
    expect($uri)->toBe('/'.$route->uri())->toMatch('~^/livewire-[a-f0-9]{8}/update$~');
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->assertOk()->getContent();
    preg_match('/data-update-uri="([^"]+)"/', $html, $match);
    expect(parse_url(html_entity_decode($match[1]), PHP_URL_PATH))->toBe($uri);
    expect(substr_count($html, 'data-update-uri='))->toBe(1);
    expect($html)->toContain('wire:submit="save"', 'type="submit"');
    $this->getJson($uri, ['X-Livewire' => '1'])->assertStatus(405)->assertHeader('Allow', 'POST');
});

test('real HTTP theme updates keep POST and persist every preset without a redirect or APP_KEY change', function ($preset) {
    $keyHash = hash('sha256', config('app.key'));
    $admin = User::factory()->create(['role' => 'superadmin']);
    $html = $this->actingAs($admin)->get('/admin/settings')->assertOk()->getContent();
    $colors = PublicTheme::presets()[$preset];
    PublicTheme::resolve();
    expect(Cache::has(PublicTheme::CACHE_KEY))->toBeTrue();
    $response = $this->postJson(app('livewire')->getUpdateUri(), themeTransportPayload($html, ['colors' => $colors]), ['X-Livewire' => '1']);
    $response->assertOk()->assertHeaderMissing('Location');
    expect($response->json('components.0.effects.dispatches.0.name'))->toBe('public-theme-saved');
    expect(PublicTheme::resolve())->toBe($colors);
    $this->seed(PublicThemeSettingsSeeder::class);
    expect(PublicTheme::resolve())->toBe($colors);
    expect(hash('sha256', config('app.key')))->toBe($keyHash);
    $this->get('/')->assertOk()->assertSee('--public-primary: '.$colors['primary'].';', false);
})->with(['Besofton Classic', 'Modern Blue', 'Forest Green', 'Red White']);

test('HTTP invalid HEX does not partially update theme or redirect', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->getContent();
    $response = $this->postJson(app('livewire')->getUpdateUri(), themeTransportPayload($html, ['colors.primary' => 'red; background:url(unsafe)', 'colors.accent' => '#123456']), ['X-Livewire' => '1']);
    $response->assertOk()->assertHeaderMissing('Location');
    $snapshot = json_decode($response->json('components.0.snapshot'), true);
    expect($snapshot['memo']['errors'])->toHaveKey('colors.primary');
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
});

test('preset property sync followed by save preserves the signed settings origin across repeated HTTP requests', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->assertOk()->getContent();
    $component = themeTransportPayload($html)['components'][0];
    $original = json_decode($component['snapshot'], true)['memo'];
    $presets = PublicTheme::presets();
    $uri = app('livewire')->getUpdateUri();

    foreach (['Besofton Classic', 'Forest Green', 'Modern Blue', 'Red White', 'Besofton Classic', 'Red White', 'Red White'] as $preset) {
        $before = Setting::query()->where('key', 'like', 'theme_%')->pluck('value', 'key')->all();
        // Exercise the two-request case too: property synchronization is not Save.
        $component['updates'] = ['colors' => $presets[$preset]];
        $component['calls'] = [];
        $sync = $this->postJson($uri, ['components' => [$component]], ['X-Livewire' => '1', 'Accept' => '*/*']);
        $sync->assertOk()->assertHeaderMissing('Location');
        expect($sync->json('components.0.effects'))->not->toHaveKey('redirect');
        expect(Setting::query()->where('key', 'like', 'theme_%')->pluck('value', 'key')->all())->toBe($before);

        $component['snapshot'] = $sync->json('components.0.snapshot');
        $component['updates'] = [];
        $component['calls'] = [['path' => '', 'method' => 'save', 'params' => []]];
        $save = $this->postJson($uri, ['components' => [$component]], ['X-Livewire' => '1', 'Accept' => '*/*']);
        $save->assertOk()->assertHeaderMissing('Location');
        $effects = $save->json('components.0.effects');
        expect($effects)->not->toHaveKey('redirect');
        expect(array_column($effects['dispatches'], 'name'))->toBe(['public-theme-saved']);
        foreach ([$sync, $save] as $response) {
            $memo = json_decode($response->json('components.0.snapshot'), true)['memo'];
            expect(array_intersect_key($memo, array_flip(['id', 'name', 'path', 'method'])))
                ->toBe(array_intersect_key($original, array_flip(['id', 'name', 'path', 'method'])));
            expect($memo['path'])->toBe('admin/settings');
            expect($memo['method'])->toBe('GET');
            expect($memo['errors'])->toBe([]);
        }
        foreach ($presets[$preset] as $token => $color) {
            expect(Setting::query()->whereKey(PublicTheme::key($token))->value('value'))->toBe($color);
        }
        PublicTheme::forget();
        expect(PublicTheme::resolve())->toBe($presets[$preset]);
        expect(PublicTheme::presets())->toBe($presets);
        $component['snapshot'] = $save->json('components.0.snapshot');
    }
});

test('expired authentication rejects a signed admin snapshot with JSON 401 and no redirect', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->getContent();
    $payload = themeTransportPayload($html, ['colors.primary' => '#123456']);
    $this->app['auth']->forgetGuards();
    $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1', 'Accept' => '*/*'])->assertUnauthorized()->assertHeaderMissing('Location');
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
});

test('user without settings permission cannot replay an authorized admin snapshot', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->getContent();
    $this->actingAs(User::factory()->create(['role' => 'editor']));
    $this->postJson(app('livewire')->getUpdateUri(), themeTransportPayload($html), ['X-Livewire' => '1'])->assertForbidden()->assertHeaderMissing('Location');
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
});

test('Livewire keeps CSRF protection when unit test bypass is disabled', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->getContent();
    $payload = themeTransportPayload($html);
    $this->app->instance('env', 'production');
    $this->withSession(['_token' => 'test-only-csrf-token']);
    $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1'])->assertStatus(419)->assertHeaderMissing('Location');
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
});

test('405 diagnostics contain only transport metadata and do not expose request secrets', function () {
    Log::spy();
    $uri = app('livewire')->getUpdateUri();
    $this->getJson($uri.'?token=sensitive-query', ['X-Livewire' => '1', 'Cookie' => 'session=sensitive-cookie', 'X-CSRF-TOKEN' => 'sensitive-token'])->assertStatus(405);
    Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) {
        $serialized = json_encode($context);

        return $message === 'Livewire transport failure.'
            && $context['method'] === 'GET' && $context['server_method'] === 'GET' && $context['status'] === 405
            && ! str_contains($serialized, 'sensitive') && count($context) === 13
            && $context['verified_origins'] === [];
    });
});

test('HTTPS requests render HTTPS update URLs without accepting spoofed proxy headers', function () {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $uri = app('livewire')->getUpdateUri();
    $this->get('https://localhost/admin/settings', ['X-Forwarded-Proto' => 'http', 'X-Forwarded-Host' => 'attacker.invalid'])->assertOk()->assertSee('data-update-uri="https://localhost'.$uri.'"', false);
});

test('diagnostics distinguish an incoming POST from an internal GET route rejection without logging arguments', function () {
    Log::spy();
    $request = Request::create(app('livewire')->getUpdateUri(), 'POST', ['snapshot' => 'sensitive-payload']);
    $request->headers->set('X-Livewire', '1');
    $exception = new MethodNotAllowedHttpException(['POST'], 'The GET method is not supported for this route.');
    $response = (new JsonResponse(['message' => 'Rejected'], 405))->withException($exception);
    $middleware = new LogLivewireTransportFailures;
    expect($middleware->handle($request, fn () => $response))->toBe($response);
    expect($response->getStatusCode())->toBe(405);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $context['entry_method'] === 'POST' && $context['server_method'] === 'POST'
        && $context['rejected_method'] === 'GET' && count($context['routing_frames']) <= 5
        && ! str_contains(json_encode($context), 'sensitive-payload')
    );
});

test('failed internal routing logs only verified origin categories and preserves the original HTTP POST', function () {
    $html = $this->actingAs(User::factory()->create(['role' => 'superadmin']))->get('/admin/settings')->assertOk()->getContent();
    $payload = themeTransportPayload($html, ['colors' => PublicTheme::presets()['Red White']]);
    Log::spy();
    // Inject a routing failure after verification; do not edit or re-sign snapshots.
    $stop = \Livewire\after('snapshot-verified', function (): void {
        throw new MethodNotAllowedHttpException(['POST'], 'The GET method is not supported for this route.');
    });
    try {
        $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1', 'Accept' => '*/*'])
            ->assertStatus(405)->assertHeaderMissing('Location');
    } finally {
        $stop();
    }
    expect(Setting::query()->where('key', 'like', 'theme_%')->count())->toBe(0);
    Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $context['entry_method'] === 'POST'
        && $context['server_method'] === 'POST' && $context['rejected_method'] === 'GET'
        && $context['verified_origins'] === [['component' => 'public-theme-editor', 'origin' => 'settings', 'method' => 'GET']]
        && ! str_contains(json_encode($context), 'checksum')
    );
    $this->postJson(app('livewire')->getUpdateUri(), $payload, ['X-Livewire' => '1', 'Accept' => '*/*'])->assertOk();
    expect(PublicTheme::resolve())->toBe(PublicTheme::presets()['Red White']);
});
