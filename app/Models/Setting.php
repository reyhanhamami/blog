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
            Cache::memo()->forget('settings:values');
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
        $values = Cache::memo()->remember('settings:values', 3600, fn () => static::query()->pluck('value', 'key')->all());

        return (string) ($values[$key] ?? $default);
    }

    public static function putValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting:'.$key);
    }
}
