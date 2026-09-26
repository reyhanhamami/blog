<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public const FIELDS = ['site_name' => 'Nama website', 'tagline' => 'Tagline', 'logo_url' => 'URL logo', 'favicon_url' => 'URL favicon', 'default_description' => 'Deskripsi SEO default', 'google_verification' => 'Token verifikasi Google'];

    public function edit()
    {
        Gate::authorize('manage-system');
        $values = [];
        foreach (self::FIELDS as $key => $label) {
            $values[$key] = Setting::valueFor($key, $key === 'site_name' ? 'Besofton Insights' : '');
        }

        return view('admin.settings', compact('values'));
    }

    public function update(Request $request)
    {
        Gate::authorize('manage-system');
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:191'], 'tagline' => ['nullable', 'string', 'max:191'],
            'logo_url' => ['nullable', 'url', 'max:2048'], 'favicon_url' => ['nullable', 'url', 'max:2048'],
            'default_description' => ['nullable', 'string', 'max:300'], 'google_verification' => ['nullable', 'string', 'max:191'],
        ]);
        foreach (self::FIELDS as $key => $label) {
            Setting::putValue($key, $data[$key] ?? '');
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
