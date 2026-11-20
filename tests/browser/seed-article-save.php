<?php

use App\Models\Post;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'article-save-qa.sqlite')) {
    throw new RuntimeException('Article save QA requires its isolated SQLite file.');
}
Artisan::call('migrate', ['--force' => true]);
PermissionCatalog::seedDefaults();
$admin = User::firstOrCreate(['email' => 'article-save@example.test'], ['name' => 'Save QA', 'role' => 'superadmin', 'password' => Hash::make('Article-Save-QA-local-only')]);
Post::firstOrCreate(['slug' => 'article-save-existing'], ['title' => 'Existing save QA', 'content' => '<p>Original article.</p>', 'status' => 'draft', 'created_by' => $admin->id]);
echo "Isolated article save QA ready.\n";
