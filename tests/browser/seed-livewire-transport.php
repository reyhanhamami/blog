<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Existing helper refuses all databases except the isolated QA SQLite filename.
require __DIR__.'/seed-public-presentation.php';
User::firstOrCreate(['email' => 'theme-editor-qa@example.test'], ['name' => 'Editor QA', 'role' => 'editor', 'password' => Hash::make('Theme-QA-local-only')]);
echo "Livewire transport QA users ready.\n";
