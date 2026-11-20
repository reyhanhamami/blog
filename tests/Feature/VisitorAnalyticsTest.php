<?php

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Services\Analytics\AnalyticsReport;
use App\Services\Analytics\ReportPeriod;
use App\Services\Analytics\VisitorTracker;
use App\Support\PermissionCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'Asia/Jakarta'));
    DB::table('analytics_state')->update(['started_at' => '2026-11-01 00:00:00']);
});

function analyticsPost(array $attributes = []): Post
{
    return Post::create(array_merge(['title' => 'Artikel analytics', 'slug' => 'artikel-analytics', 'status' => 'published', 'published_at' => now()->subDays(30), 'content' => '<p>Konten publik analytics.</p>', 'views' => 80, 'legacy_views' => 80], $attributes));
}

function analyticsEvent(string $visitor, string $path = '/', ?int $post = null, ?string $id = null): void
{
    app(VisitorTracker::class)->record(hash('sha256', $visitor), $path, $post, $id ?? hash('sha256', (string) Str::uuid()));
}

function analyticsPeriod(string $from = '2026-11-01', string $to = '2026-11-20'): ReportPeriod
{
    return ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => 'custom', 'from' => $from, 'to' => $to]));
}

test('article counter preserves legacy and editorial timestamps and only confirmed displays count', function () {
    $post = analyticsPost();
    $stamp = $post->updated_at;
    $visitor = str_repeat('a', 64);
    $response = $this->withCredentials()->withHeader('User-Agent', 'Mozilla/5.0 Browser')->withCookie(config('analytics.cookie'), $visitor)->get('/artikel-analytics')->assertOk();
    expect($post->fresh()->views)->toBe(80);
    preg_match('/data-visit-receipt="([^"]+)"/', $response->getContent(), $match);
    expect($match)->toHaveCount(2);
    $receipt = html_entity_decode($match[1]);
    $packet = ['receipt' => $receipt, 'id' => (string) Str::uuid()];
    $this->postJson('/visits/confirm', $packet)->assertNoContent();
    $this->postJson('/visits/confirm', $packet)->assertNoContent();
    expect($post->fresh()->views)->toBe(81);
    expect($post->fresh()->legacy_views)->toBe(80);
    expect($post->fresh()->tracked_views)->toBe(1);
    expect($post->fresh()->updated_at->equalTo($stamp))->toBeTrue();
    expect(DB::table('analytics_daily')->where('page_key', 'site')->sum('views'))->toBe(1);
    $this->travel(11)->seconds();
    $this->postJson('/visits/confirm', ['receipt' => $receipt, 'id' => (string) Str::uuid()])->assertNoContent();
    expect($post->fresh()->views)->toBe(82);
    expect(DB::table('analytics_site_visitors')->count())->toBe(1);
});

test('refresh cooldown and request idempotency deduplicate without losing separate visitors', function () {
    $post = analyticsPost();
    $sameReceipt = hash('sha256', 'same-request');
    foreach (range(1, 10) as $attempt) {
        analyticsEvent('one', '/artikel-analytics', $post->id, $sameReceipt);
    }
    analyticsEvent('one', '/artikel-analytics', $post->id); // A second request inside 10 seconds.
    analyticsEvent('two', '/artikel-analytics', $post->id);
    expect($post->fresh()->views)->toBe(82);
    expect(DB::table('analytics_daily')->where('post_id', $post->id)->value('visitors'))->toBe(2);
    $this->travel(10)->seconds();
    analyticsEvent('one', '/artikel-analytics', $post->id);
    expect($post->fresh()->views)->toBe(83);
    expect(DB::table('analytics_daily')->where('post_id', $post->id)->value('visitors'))->toBe(2);
});

test('one browser on multiple articles has one site visitor and one unique for each article', function () {
    $one = analyticsPost();
    $two = analyticsPost(['slug' => 'kedua']);
    analyticsEvent('same', '/artikel-analytics', $one->id);
    analyticsEvent('same', '/kedua', $two->id);
    analyticsEvent('same');
    $summary = app(AnalyticsReport::class)->summary(analyticsPeriod());
    expect($summary['views'])->toBe(3);
    expect($summary['unique'])->toBe(1);
    expect($summary['articleViews'])->toBe(2);
    expect(DB::table('analytics_daily')->where('page_key', 'site')->sum('visitors'))->toBe(1);
    expect(app(AnalyticsReport::class)->article($two)['unique'])->toBe(1);
});

test('period uniques are distinct across days and daily averages include zero days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Asia/Jakarta'));
    analyticsEvent('repeat');
    $this->travel(1)->days();
    analyticsEvent('repeat');
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00', 'Asia/Jakarta'));
    $summary = app(AnalyticsReport::class)->summary(analyticsPeriod('2026-11-01', '2026-11-03'));
    expect($summary['unique'])->toBe(1);
    expect($summary['views'])->toBe(2);
    expect($summary['averageVisitors'])->toBe(2 / 3);
    expect($summary['averageViews'])->toBe(2 / 3);
    expect($summary['series'][2])->toBe(['date' => '2026-11-03', 'views' => 0, 'visitors' => 0]);
    expect($summary['windows']['today'])->toBe(0);
});

test('empty periods and zero previous periods do not create fictional growth', function () {
    $summary = app(AnalyticsReport::class)->summary(analyticsPeriod('2026-11-10', '2026-11-12'));
    expect($summary['views'])->toBe(0);
    expect($summary['unique'])->toBe(0);
    expect($summary['averageVisitors'])->toBe(0);
    expect($summary['growth'])->toBe(0);
    expect($summary['series'])->toHaveCount(3);
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'Asia/Jakarta'));
    analyticsEvent('new');
    $summary = app(AnalyticsReport::class)->summary(analyticsPeriod('2026-11-10', '2026-11-12'));
    expect($summary['previous'])->toBe(0);
    expect($summary['growth'])->toBeNull();
});

test('Jakarta midnight groups days correctly while timestamps remain UTC', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-19 16:59:59', 'UTC'));
    analyticsEvent('same');
    $this->travel(2)->seconds();
    analyticsEvent('same');
    expect(DB::table('analytics_daily')->where('page_key', 'site')->orderBy('date')->pluck('date')->all())->toBe(['2026-11-19', '2026-11-20']);
    expect(DB::table('analytics_daily')->where('page_key', 'site')->where('date', '2026-11-20')->value('last_seen_at'))->toBe('2026-11-19 17:00:01');
    expect(app(AnalyticsReport::class)->summary(analyticsPeriod())['unique'])->toBe(1);
});

test('bots HEAD prefetch opt out assets errors private and preview never receive a receipt', function (string $path, string $method, array $headers) {
    analyticsPost();
    $this->withHeaders(array_merge(['User-Agent' => 'Mozilla/5.0 Browser'], $headers));
    $response = $method === 'HEAD' ? $this->head($path) : $this->get($path);
    $response->assertDontSee('data-visit-receipt', false);
    expect(DB::table('analytics_daily')->count())->toBe(0);
})->with([
    ['/', 'GET', ['User-Agent' => 'Googlebot/2.1']], ['/', 'HEAD', []], ['/', 'GET', ['Purpose' => 'prefetch']],
    ['/', 'GET', ['Sec-Purpose' => 'prefetch;prerender']], ['/', 'GET', ['DNT' => '1']], ['/', 'GET', ['Sec-GPC' => '1']],
    ['/feed.xml', 'GET', []], ['/robots.txt', 'GET', []], ['/missing-article', 'GET', []], ['/cari?q=test', 'GET', []], ['/login', 'GET', []],
]);

test('receipt cannot be used by a different browser and draft or expired receipt cannot count', function () {
    $post = analyticsPost(['status' => 'draft']);
    $visitor = str_repeat('b', 64);
    $tracker = app(VisitorTracker::class);
    $this->withCredentials()->withHeader('User-Agent', 'Mozilla/5.0 Browser')->withCookie(config('analytics.cookie'), $visitor);
    $receipt = $tracker->receipt(str_repeat('c', 64), '/', null);
    $this->postJson('/visits/confirm', ['receipt' => $receipt, 'id' => (string) Str::uuid()])->assertNoContent();
    $receipt = $tracker->receipt($visitor, '/draft', $post->id);
    $this->postJson('/visits/confirm', ['receipt' => $receipt, 'id' => (string) Str::uuid()])->assertNoContent();
    $receipt = $tracker->receipt($visitor, '/', null);
    $this->travel(25)->hours();
    $this->postJson('/visits/confirm', ['receipt' => $receipt, 'id' => (string) Str::uuid()])->assertNoContent();
    expect(DB::table('analytics_daily')->count())->toBe(0);
});

test('cookie is anonymous secure httponly same site and no permanent raw IP or user agent is stored', function () {
    config(['app.env' => 'production']);
    $response = $this->withHeader('User-Agent', 'Mozilla/5.0 Browser')->get('https://localhost/')->assertOk();
    $cookie = $response->getCookie(config('analytics.cookie'));
    expect($cookie)->not->toBeNull();
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->isSecure())->toBeTrue();
    expect($cookie->getSameSite())->toBe('lax');
    expect($cookie->getValue())->toMatch('/\A[a-f0-9]{64}\z/');
    analyticsEvent('visitor');
    foreach (['analytics_page_visitors', 'analytics_site_visitors', 'analytics_receipts'] as $table) {
        $json = json_encode(DB::table($table)->get());
        expect($json)->not->toContain('127.0.0.1', 'Mozilla/', 'session', 'email');
    }
});

test('tracking persistence failure returns no content and logs only safe operational context', function () {
    Log::spy();
    $this->mock(VisitorTracker::class, fn ($mock) => $mock->shouldReceive('confirm')->once()->andThrow(new RuntimeException('sensitive request information')));
    $this->postJson('/visits/confirm', [])->assertNoContent();
    Log::shouldHaveReceived('error')->with('Visitor analytics write failed', ['exception_type' => RuntimeException::class, 'code' => '0'])->once();
});

test('only superadmin accesses dashboard export print even when every permission is assigned', function (string $role) {
    PermissionCatalog::seedDefaults();
    $record = Role::firstOrCreate(['name' => $role], ['display_name' => $role]);
    $record->permissions()->sync(DB::table('permissions')->pluck('id')->all());
    $user = User::factory()->create(['role' => $role]);
    foreach (['/admin/analytics', '/admin/analytics/export', '/admin/analytics/print'] as $path) {
        $response = $this->actingAs($user)->get($path);
        if ($role === 'superadmin') {
            $response->assertOk();
        } else {
            $response->assertForbidden()->assertDontSee('data-analytics-chart', false);
        }
    }
})->with(['superadmin', 'editor', 'author', 'reader', 'admin', 'custom-admin']);

test('guest cannot directly access stats export or print', function () {
    foreach (['/admin/analytics', '/admin/analytics/export', '/admin/analytics/print'] as $path) {
        $this->get($path)->assertRedirect('/login');
    }
});

test('non superadmins see no visitor counts menus panels or hidden serialization in CMS or public', function () {
    PermissionCatalog::seedDefaults();
    $role = Role::where('name', 'editor')->firstOrFail();
    $role->permissions()->sync(DB::table('permissions')->pluck('id')->all());
    $post = analyticsPost(['views' => 987654321, 'legacy_views' => 987654321]);
    $user = User::factory()->create(['role' => 'editor']);
    foreach (['/admin', '/admin/posts', '/admin/posts/'.$post->id.'/edit'] as $path) {
        $this->actingAs($user)->get($path)->assertOk()->assertDontSee('987.654.321')->assertDontSee('Statistik Artikel')->assertDontSee('Visitor Analytics')->assertDontSee('data-analytics-chart', false);
    }
    $this->get('/artikel-analytics')->assertOk()->assertDontSee('987654321')->assertDontSee('data-visit-receipt', false);
    expect($post->toArray())->not->toHaveKeys(['views', 'legacy_views', 'tracked_views', 'last_viewed_at']);
    $this->get('/admin/posts/'.$post->id.'/preview')->assertOk()->assertDontSee('data-visit-receipt', false);
});

test('superadmin article table panel search sorting filter print and CSV use actual period data', function () {
    $one = analyticsPost(['title' => '=Formula Title']);
    $two = analyticsPost(['slug' => 'kedua', 'title' => 'Artikel kedua']);
    analyticsEvent('one', '/artikel-analytics', $one->id);
    analyticsEvent('two', '/artikel-analytics', $one->id);
    analyticsEvent('one', '/kedua', $two->id);
    $this->actingAs(User::factory()->create(['role' => 'superadmin']));
    $this->get('/admin/analytics?period=today&sort=period_unique')->assertOk()->assertSee('Advertising Insights')->assertSee('Top 10 Most Viewed Articles')->assertSee('=Formula Title');
    $this->get('/admin/analytics?q=tidak-ada')->assertOk()->assertSee('Tidak ada artikel yang cocok.');
    $this->get('/admin/posts')->assertOk()->assertSee('Views');
    $this->get('/admin/posts/'.$one->id.'/edit')->assertOk()->assertSee('Statistik Artikel')->assertSee('data-article-statistics', false);
    $this->get('/admin/analytics/print')->assertOk()->assertSee('Besofton Insights')->assertSee('Definisi dan cakupan');
    $csv = $this->get('/admin/analytics/export?period=today')->assertOk()->streamedContent();
    expect($csv)->toContain('Besofton Insights', 'unique;2', 'views;3', "'=Formula Title", '2026-11-20;3;2');
    expect($csv)->not->toContain("\n=Formula Title");
});

test('invalid or unsupported report periods are rejected before expensive queries', function (array $query) {
    $this->actingAs(User::factory()->create(['role' => 'superadmin']))->getJson('/admin/analytics?'.http_build_query($query))->assertStatus(422);
})->with([
    [['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-11-20']],
    [['period' => 'custom', 'from' => '2026-11-20', 'to' => '2026-11-19']],
    [['period' => 'custom', 'from' => '2026-11-20', 'to' => '2026-11-21']],
    [['period' => 'custom', 'from' => 'bad', 'to' => '2026-11-20']],
    [['sort' => 'views desc;drop table posts']],
]);

test('retention deletes only expired detail and aggregates while preserving all time counters', function () {
    $post = analyticsPost();
    DB::table('analytics_site_visitors')->insert(['date' => '2026-01-01', 'visitor_hash' => hash('sha256', 'old')]);
    DB::table('analytics_daily')->insert(['date' => '2026-01-01', 'page_key' => 'site', 'views' => 50, 'visitors' => 20]);
    DB::table('analytics_daily')->insert(['date' => '2020-01-01', 'page_key' => 'site', 'views' => 5, 'visitors' => 2]);
    analyticsEvent('current', '/artikel-analytics', $post->id);
    $this->artisan('analytics:prune')->assertSuccessful();
    expect(DB::table('analytics_site_visitors')->where('date', '2026-01-01')->count())->toBe(0);
    expect(DB::table('analytics_daily')->where('date', '2026-01-01')->sum('views'))->toBe(50);
    expect(DB::table('analytics_daily')->where('date', '2020-01-01')->count())->toBe(0);
    expect($post->fresh()->views)->toBe(81);
});

test('additive migration snapshots legacy without resetting views and rollback preserves combined counter', function () {
    $post = analyticsPost(['views' => 1234]);
    $migration = require database_path('migrations/2026_11_20_000001_create_visitor_analytics.php');
    $migration->down();
    expect(DB::table('posts')->where('id', $post->id)->value('views'))->toBe(1234);
    $migration->up();
    expect($post->fresh()->views)->toBe(1234);
    expect($post->fresh()->legacy_views)->toBe(1234);
    expect($post->fresh()->tracked_views)->toBe(0);
    expect(DB::table('analytics_daily')->count())->toBe(0);
});
