<?php

namespace App\Models;

use App\Support\PublicTheme;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected static function booted(): void
    {
        $invalidate = function (Setting $setting) {
            Cache::forget('setting:'.$setting->key);
            if (str_starts_with($setting->key, 'theme_')) {
                PublicTheme::forget();
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function valueFor(string $key, string $default = ''): string
    {
        return Cache::remember('setting:'.$key, 3600, fn () => (string) (static::query()->whereKey($key)->value('value') ?? $default));
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting:'.$key);
    }
}
