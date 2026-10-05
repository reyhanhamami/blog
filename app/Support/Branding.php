<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Branding
{
    public static function logoUrl(): string
    {
        return self::url('logo_path', 'logo_url', Storage::disk('public')->url('logo.png'));
    }

    public static function faviconUrl(): string
    {
        return self::url('favicon_path', 'favicon_url', asset('favicon.svg'));
    }

    public static function uploadedPath(string $key): ?string
    {
        $path = Setting::valueFor($key);

        return preg_match('~^uploads/branding/(?:logo|favicon)-[a-f0-9-]+\.(?:png|jpe?g|webp|ico)$~', $path) && File::exists(public_path($path)) ? $path : null;
    }

    public static function deleteOldUpload(?string $path): void
    {
        if ($path && preg_match('~^uploads/branding/(?:logo|favicon)-[a-f0-9-]+\.(?:png|jpe?g|webp|ico)$~', $path)) {
            File::delete(public_path($path));
        }
    }

    private static function url(string $pathKey, string $urlKey, string $default): string
    {
        if ($path = self::uploadedPath($pathKey)) {
            return asset($path);
        }

        return Setting::valueFor($urlKey) ?: $default;
    }
}
