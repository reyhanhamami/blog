<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PermissionCatalog
{
    public static function groups(): array
    {
        return config('permissions.groups');
    }

    public static function names(): array
    {
        return collect(self::groups())->flatMap(fn ($items) => $items)->keys()->all();
    }

    public static function normalize(array $names): array
    {
        $valid = array_fill_keys(self::names(), true);
        $selected = array_fill_keys(array_filter($names, fn ($name) => isset($valid[$name])), true);
        foreach (array_keys($selected) as $name) {
            if ($name !== 'cms.access') {
                $selected['cms.access'] = true;
                $selected['dashboard.view'] = true;
            }
            $module = Str::before($name, '.');
            $view = $module.'.view';
            if ($name !== $view && isset($valid[$view])) {
                $selected[$view] = true;
            }
            foreach (config('permissions.dependencies.'.$name, []) as $dependency) {
                $selected[$dependency] = true;
            }
        }

        return array_keys($selected);
    }

    public static function seedDefaults(): void
    {
        DB::transaction(function () {
            foreach (self::groups() as $group => $items) {
                foreach ($items as $name => $label) {
                    Permission::firstOrCreate(['name' => $name], ['display_name' => $label, 'group_name' => $group, 'description' => config('permissions.descriptions.'.$name)]);
                }
            }

            $all = Permission::pluck('id', 'name');
            $descriptions = [
                'superadmin' => 'Akses penuh ke seluruh sistem.',
                'admin' => 'Mengelola konten, pengguna, dan konfigurasi CMS.',
                'editor' => 'Meninjau dan menerbitkan konten editorial.',
                'author' => 'Membuat serta mengubah artikel miliknya sendiri.',
                'reader' => 'Mengakses akun dan fitur pembaca publik.',
            ];
            foreach (config('permissions.defaults') as $name => $patterns) {
                $role = Role::firstOrCreate(['name' => $name], [
                    'display_name' => ucfirst($name), 'description' => $descriptions[$name], 'is_system' => true,
                ]);
                if ($role->wasRecentlyCreated && $name !== 'superadmin') {
                    $names = collect(self::names())->filter(fn ($permission) => collect($patterns)->contains(fn ($pattern) => Str::is($pattern, $permission)))->all();
                    $role->permissions()->sync($all->only(self::normalize($names))->values()->all());
                }
            }

            // Keep any preexisting custom role string attached to its users.
            foreach (DB::table('users')->whereNotNull('role')->distinct()->pluck('role') as $name) {
                Role::firstOrCreate(['name' => $name], ['display_name' => Str::headline($name), 'description' => null, 'is_system' => false]);
            }
        });
    }
}
