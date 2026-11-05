<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\PublicTheme;
use Illuminate\Database\Seeder;

class PublicThemeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PublicTheme::defaults() as $token => $default) {
            Setting::firstOrCreate(['key' => PublicTheme::key($token)], ['value' => $default]);
        }
        PublicTheme::forget();
    }
}
