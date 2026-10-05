<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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

        $logoUrl = Branding::logoUrl();
        $faviconUrl = Branding::faviconUrl();

        return view('admin.settings', compact('values', 'logoUrl', 'faviconUrl'));
    }

    public function update(Request $request)
    {
        Gate::authorize('manage-system');
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:191'], 'tagline' => ['nullable', 'string', 'max:191'],
            'logo_url' => ['nullable', 'url', 'max:2048'], 'favicon_url' => ['nullable', 'url', 'max:2048'],
            'default_description' => ['nullable', 'string', 'max:300'], 'google_verification' => ['nullable', 'string', 'max:191'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096', 'dimensions:min_width=100,max_width=4000,max_height=4000'],
            'favicon' => ['nullable', 'file', 'max:1024'],
        ]);
        foreach (['logo', 'favicon'] as $kind) {
            if (isset($data[$kind])) {
                $this->validateImage($data[$kind], $kind);
            }
        }

        $oldPaths = [];
        $newPaths = [];
        try {
            foreach (['logo', 'favicon'] as $kind) {
                if (! isset($data[$kind])) {
                    continue;
                }
                $oldPaths[$kind] = Branding::uploadedPath($kind.'_path');
                $extension = $kind === 'favicon' ? strtolower($data[$kind]->getClientOriginalExtension()) : $data[$kind]->guessExtension();
                $filename = $kind.'-'.Str::uuid().'.'.$extension;
                File::ensureDirectoryExists(public_path('uploads/branding'));
                $data[$kind]->move(public_path('uploads/branding'), $filename);
                $newPaths[$kind] = 'uploads/branding/'.$filename;
            }
            DB::transaction(function () use ($data, $newPaths) {
                foreach (self::FIELDS as $key => $label) {
                    Setting::putValue($key, $data[$key] ?? '');
                }
                foreach ($newPaths as $kind => $path) {
                    Setting::putValue($kind.'_path', $path);
                }
            });
        } catch (\Throwable $exception) {
            foreach ($newPaths as $path) {
                File::delete(public_path($path));
            }
            throw $exception;
        }
        foreach ($oldPaths as $path) {
            Branding::deleteOldUpload($path);
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }

    private function validateImage(UploadedFile $file, string $kind): void
    {
        $name = $file->getClientOriginalName();
        if (preg_match('/(?:^|\.)(?:php|phtml|phar)(?:\.|$)/i', $name)) {
            throw ValidationException::withMessages([$kind => 'Nama file tidak diizinkan.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $dimensions = @getimagesize($file->getRealPath());
        if ($kind === 'logo') {
            if (! $dimensions || ! in_array($dimensions['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
                throw ValidationException::withMessages([$kind => 'Logo harus berupa PNG, JPG, JPEG, atau WebP yang valid.']);
            }

            return;
        }

        $bytes = file_get_contents($file->getRealPath());
        $isPng = $extension === 'png' && ($dimensions['mime'] ?? '') === 'image/png' && ($dimensions[0] ?? 0) >= 32 && ($dimensions[1] ?? 0) >= 32;
        $isIco = false;
        if ($extension === 'ico' && strlen($bytes) >= 26 && substr($bytes, 0, 4) === "\x00\x00\x01\x00") {
            $count = unpack('v', substr($bytes, 4, 2))[1];
            $size = unpack('V', substr($bytes, 14, 4))[1];
            $offset = unpack('V', substr($bytes, 18, 4))[1];
            $imageHeader = substr($bytes, $offset, 8);
            $isIco = $count > 0 && $count <= 256 && $offset >= 6 + $count * 16 && $size >= 40 && $offset + $size <= strlen($bytes)
                && (str_starts_with($imageHeader, "\x89PNG\r\n\x1a\n") || in_array(unpack('V', substr($imageHeader, 0, 4))[1], [40, 108, 124], true));
        }
        if (! $isPng && ! $isIco) {
            throw ValidationException::withMessages([$kind => 'Favicon harus berupa PNG minimal 32×32 piksel atau ICO yang valid.']);
        }
    }
}
