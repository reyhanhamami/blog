<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Support\PublicNavigation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class HeaderNavigationSeeder extends Seeder
{
    public function run(): void
    {
        $menu = Menu::firstOrCreate(['location' => 'header'], ['name' => 'Header Menu']);

        foreach (PublicNavigation::CORE as $url => $label) {
            $menu->items()->firstOrCreate(['url' => $url], [
                'label' => $label,
                'sort_order' => array_search($url, array_keys(PublicNavigation::CORE), true) * 10,
                'is_active' => true,
            ]);
        }

        // Only migrate the exact defaults from the older core seeder.
        $menu->items()->where('url', '/')->where('label', 'Beranda')->where('sort_order', 0)->update(['label' => 'Insights']);
        $menu->items()->where('url', '/cari')->where('label', 'Cari')->where('sort_order', 0)->update(['is_active' => false]);
        Cache::forget('menu:header');
    }
}
