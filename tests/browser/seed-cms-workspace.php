<?php

use App\Models\Author;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

// This fixture can only migrate the explicitly selected local QA SQLite file.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'cms-workspace-qa.sqlite')) {
    throw new RuntimeException('QA requires the isolated SQLite database.');
}
Artisan::call('migrate', ['--force' => true]);
PermissionCatalog::seedDefaults();
User::firstOrCreate(['email' => 'workspace-qa@example.test'], ['name' => 'Workspace QA', 'role' => 'superadmin', 'password' => Hash::make('Workspace-QA-local-only')]);
Author::firstOrCreate(['slug' => 'workspace-qa'], ['name' => 'Workspace QA']);
echo "Isolated browser fixture ready.\n";
