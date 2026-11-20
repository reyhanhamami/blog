<?php

use App\Models\Post;
use App\Services\Analytics\VisitorTracker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (($argv[1] ?? '') === 'worker') {
    if (config('database.default') !== 'mysql' || ! preg_match('/\Abesofton_analytics_qa_[a-f0-9]{12}\z/', config('database.connections.mysql.database'))) {
        throw new RuntimeException('Worker requires an isolated analytics MySQL database.');
    }
    app(VisitorTracker::class)->record(hash('sha256', $argv[2]), '/concurrency-article', 1, hash('sha256', $argv[3]));
    exit;
}
$mysql = config('database.connections.mysql');
if (! in_array($mysql['host'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('Concurrency integration test only supports local MySQL.');
}
$database = 'besofton_analytics_qa_'.bin2hex(random_bytes(6));
$server = new PDO('mysql:host='.$mysql['host'].';port='.$mysql['port'].';charset=utf8mb4', $mysql['username'], $mysql['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
Artisan::call('migrate', ['--force' => true]);
Post::create(['id' => 1, 'title' => 'Concurrency QA', 'slug' => 'concurrency-article', 'status' => 'published', 'published_at' => now()->subDay(), 'views' => 120, 'legacy_views' => 120]);
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE='.$database);
putenv('DB_URL=');
// Use inherited local configuration; never put credentials in command arguments.
foreach (['DB_HOST' => $mysql['host'], 'DB_PORT' => $mysql['port'], 'DB_USERNAME' => $mysql['username'], 'DB_PASSWORD' => $mysql['password'], 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    putenv($key.'='.$value);
}
function concurrentBatch(array $jobs): void
{
    $processes = [];
    foreach ($jobs as [$visitor, $receipt]) {
        $process = proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, 'worker', $visitor, $receipt], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Concurrent worker failed: '.$output);
        }
    }
}
concurrentBatch(array_fill(0, 8, ['one-browser', 'one-request']));
if ((int) DB::table('posts')->where('id', 1)->value('views') !== 121) {
    throw new RuntimeException('Duplicate concurrent request was counted twice.');
}
concurrentBatch(array_map(fn ($number) => ['browser-'.$number, 'request-'.$number], range(1, 8)));
$post = DB::table('posts')->where('id', 1)->first();
$daily = DB::table('analytics_daily')->where('page_key', 'site')->first();
if ((int) $post->views !== 129 || (int) $post->tracked_views !== 9 || (int) $daily->views !== 9 || (int) $daily->visitors !== 9) {
    throw new RuntimeException('Concurrent increments lost an update or visitor.');
}
echo json_encode(['status' => 'PASS', 'engine' => DB::selectOne('SELECT VERSION() AS v')->v, 'duplicate_parallel_workers' => 8, 'independent_parallel_workers' => 8, 'legacy' => $post->legacy_views, 'total' => $post->views, 'tracked' => $post->tracked_views, 'views' => $daily->views, 'visitors' => $daily->visitors, 'isolated_database' => $database], JSON_THROW_ON_ERROR).PHP_EOL;
// Keep the explicitly named QA database for review. Never drop an existing DB.
