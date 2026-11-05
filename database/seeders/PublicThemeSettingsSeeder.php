<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\PublicTheme;
use Illuminate\Database\Seeder;

class PublicThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Resolves validated Classic definitions if a stale config cache lacks theme keys.
        // firstOrCreate only fills missing keys and always preserves an admin's colors.
        foreach (PublicTheme::defaults() as $token => $default) {
            Setting::firstOrCreate(['key' => PublicTheme::key($token)], ['value' => $default]);
        }
        PublicTheme::forget();
    }
}
