<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PermissionCatalog::seedDefaults();
        $admin = User::firstOrNew(['email' => env('SEED_ADMIN_EMAIL', 'admin@gmail.com')]);
        if (! $admin->exists) {
            $values = [
                'name' => 'Besofton Admin', 'role' => 'superadmin',
                'password' => Hash::make(env('SEED_DEFAULT_PASSWORD', 'asddsa123')),
            ];
            if (Schema::hasColumn('users', 'mobile_phone')) {
                $values['mobile_phone'] = '';
            }
            $admin->forceFill($values)->save();
        }
        foreach ([
            'site_name' => 'Besofton Insights',
            'tagline' => 'Ide, Panduan, Insights.',
            'default_description' => 'Artikel, tutorial, dan strategi digital dari Besofton.',
        ] as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach ([
            'about' => ['Tentang Besofton Insights', '<p>Besofton Insights adalah ruang berbagi pengetahuan teknologi, tutorial, dan pengalaman praktis dari tim Besofton.</p>'],
            'privacy-policy' => ['Kebijakan Privasi', '<p>Kami menggunakan data akun untuk menyediakan bookmark, riwayat baca, dan progres belajar. Data tidak dijual kepada pihak lain. Hubungi tim Besofton untuk permintaan terkait data pribadi Anda.</p>'],
            'editorial-policy' => ['Kebijakan Editorial', '<p>Konten dibuat oleh penulis Besofton dan ditinjau sebelum diterbitkan. Kami memperbarui artikel ketika ada perubahan fakta atau teknologi. Koreksi dapat diajukan melalui halaman kontak. Bantuan AI, bila digunakan, tetap diperiksa oleh manusia sebelum publikasi.</p>'],
        ] as $slug => [$title, $content]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'is_published' => true]);
        }
        foreach (['footer' => ['Tentang' => '/about', 'Privasi' => '/privacy-policy', 'Kebijakan editorial' => '/editorial-policy']] as $location => $links) {
            $menu = Menu::firstOrCreate(['location' => $location], ['name' => ucfirst($location).' Menu']);
            foreach ($links as $label => $url) {
                $menu->items()->firstOrCreate(['url' => $url], ['label' => $label, 'sort_order' => 0, 'is_active' => true]);
            }
        }
        $this->call(HeaderNavigationSeeder::class);
    }
}
