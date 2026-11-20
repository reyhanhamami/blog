<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'visitor-analytics-qa.sqlite')) {
    throw new RuntimeException('Analytics browser QA requires its isolated SQLite database.');
}
if (($argv[1] ?? '') === 'stats') {
    echo json_encode(['views' => (int) DB::table('analytics_daily')->where('page_key', 'site')->sum('views'), 'unique' => DB::table('analytics_site_visitors')->distinct()->count('visitor_hash'), 'post' => DB::table('posts')->where('slug', 'analytics-browser-1')->first(['views', 'legacy_views', 'tracked_views'])], JSON_THROW_ON_ERROR);
    exit;
}
Artisan::call('migrate', ['--force' => true]);
PermissionCatalog::seedDefaults();
foreach (['superadmin', 'editor'] as $role) {
    User::updateOrCreate(['email' => $role.'-analytics@example.test'], ['name' => 'QA '.$role, 'role' => $role, 'password' => Hash::make('Analytics-QA-local-only')]);
}
Role::where('name', 'editor')->firstOrFail()->permissions()->sync(DB::table('permissions')->pluck('id')->all());
$category = Category::firstOrCreate(['slug' => 'analytics-qa'], ['name' => 'Analytics QA', 'is_active' => true]);
foreach ([1, 2] as $number) {
    Post::firstOrCreate(['slug' => 'analytics-browser-'.$number], ['title' => 'Artikel Analytics '.$number, 'content' => '<h2>Materi</h2><p>Konten browser QA.</p>', 'status' => 'published', 'category_id' => $category->id, 'published_at' => now()->subDays($number), 'is_featured' => true, 'views' => 80, 'legacy_views' => 80]);
}
Setting::putValue('logo_url', '/uploads/qa-featured-slider.png');
echo "Analytics QA ready in isolated SQLite; no production or demo seed data.\n";
