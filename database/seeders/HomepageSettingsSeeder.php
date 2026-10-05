<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\HomeHeroSettings;
use Illuminate\Database\Seeder;

class HomepageSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (HomeHeroSettings::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
