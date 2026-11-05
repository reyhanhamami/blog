<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicTheme
{
    public const CACHE_KEY = 'public:theme';

    public static function groups(): array
    {
        $groups = config('public_theme.groups');
        $problem = self::groupsProblem($groups);
        if ($problem !== null) {
            self::configurationError('public_theme.groups', $problem);
            $groups = PublicThemeDefaults::GROUPS;
            // Repair this request's repository only, avoiding repeated error logs.
            // The cached config file and persisted theme settings are never changed.
            config(['public_theme.groups' => $groups]);
        }

        return $groups;
    }

    private static function groupsProblem(mixed $groups): ?string
    {
        if (! is_array($groups) || $groups === []) {
            return 'Expected a non-empty array of theme groups.';
        }
        $required = array_merge(...array_values(PublicThemeDefaults::GROUPS));
        $seen = [];
        foreach ($groups as $label => $fields) {
            if (! is_string($label) || trim($label) === '' || ! is_array($fields) || $fields === []) {
                return 'Every group must have a name and a non-empty field array.';
            }
            foreach ($fields as $token => $field) {
                if (! is_string($token) || ! array_key_exists($token, $required) || isset($seen[$token])) {
                    return 'Theme tokens must be known and unique across groups.';
                }
                if (! is_array($field) || ! array_is_list($field) || count($field) !== 3
                    || ! is_string($field[0]) || trim($field[0]) === ''
                    || ! self::valid($field[1]) || ! is_string($field[2]) || trim($field[2]) === '') {
                    return 'Every field must contain a label, a six-digit HEX default, and help text.';
                }
                $seen[$token] = true;
            }
        }

        return count($seen) === count($required) ? null : 'Required public theme tokens are missing.';
    }

    public static function shades(): array
    {
        $shades = config('public_theme_shades');
        $problem = self::shadesProblem($shades);
        if ($problem !== null) {
            self::configurationError('public_theme_shades', $problem);
            $shades = PublicThemeDefaults::SHADES;
            config(['public_theme_shades' => $shades]);
        }

        return $shades;
    }

    private static function shadesProblem(mixed $shades): ?string
    {
        if (! is_array($shades) || $shades === []) {
            return 'Expected a non-empty array of audited theme shades.';
        }
        $fields = self::fields();
        foreach ($shades as $name => $shade) {
            if (! is_string($name) || ! array_key_exists($name, PublicThemeDefaults::SHADES)
                || ! is_array($shade) || ! array_is_list($shade) || count($shade) !== 2
                || ! is_string($shade[0]) || ! array_key_exists($shade[0], $fields)
                || ! is_string($shade[1]) || preg_match('/\A#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?\z/', $shade[1]) !== 1) {
                return 'Every audited shade must have a known name, parent token, and HEX color.';
            }
        }

        return count($shades) === count(PublicThemeDefaults::SHADES) ? null : 'Required public theme shades are missing.';
    }

    private static function configurationError(string $key, string $reason): void
    {
        Log::error('Invalid public theme configuration; using bundled Besofton Classic definitions.', [
            'config_key' => $key,
            'reason' => $reason,
            'configuration_cached' => app()->configurationIsCached(),
            'action' => 'Verify tracked theme configuration files and rebuild the Laravel configuration cache.',
        ]);
    }

    public static function fields(): array
    {
        return array_merge(...array_values(self::groups()));
    }

    public static function defaults(): array
    {
        return array_map(fn (array $field) => strtolower($field[1]), self::fields());
    }

    public static function key(string $token): string
    {
        return 'theme_'.str_replace('-', '_', $token).'_color';
    }

    public static function valid(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) === 1;
    }

    public static function resolve(): array
    {
        // Validate configuration even when the resolved database theme is cached.
        $defaults = self::defaults();

        return Cache::remember(self::CACHE_KEY, 3600, function () use ($defaults) {
            $saved = Setting::query()->whereIn('key', array_map(self::key(...), array_keys($defaults)))->pluck('value', 'key');
            $colors = $defaults;
            foreach ($colors as $token => $default) {
                $key = self::key($token);
                if (! $saved->has($key)) {
                    continue;
                }
                if (self::valid($saved[$key])) {
                    $colors[$token] = strtolower($saved[$key]);
                } else {
                    // Never log untrusted CSS or render it into the page.
                    Log::warning('Invalid public theme color; using Classic fallback.', ['key' => $key]);
                }
            }

            return $colors;
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function save(array $colors): void
    {
        validator(['colors' => $colors], self::rules())->validate();
        DB::transaction(function () use ($colors) {
            foreach (self::defaults() as $token => $default) {
                Setting::putValue(self::key($token), strtolower($colors[$token]));
            }
            DB::afterCommit(fn () => self::forget());
        });
        self::forget();
    }

    public static function rules(): array
    {
        $rules = ['colors' => ['required', 'array:'.implode(',', array_keys(self::defaults()))]];
        foreach (self::defaults() as $token => $default) {
            $rules['colors.'.$token] = ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'];
        }

        return $rules;
    }

    /** Preserve audited Classic shades exactly; custom colors retain their tonal variations. */
    public static function shade(string $color, string $base, string $original): string
    {
        $result = '#';
        for ($i = 1; $i < 7; $i += 2) {
            $channel = hexdec(substr($color, $i, 2)) + hexdec(substr($original, $i, 2)) - hexdec(substr($base, $i, 2));
            $result .= sprintf('%02x', max(0, min(255, $channel)));
        }

        return $result.substr($original, 7);
    }

    public static function style(?array $colors = null): string
    {
        $colors = $colors ?? self::resolve();
        $defaults = self::defaults();
        $safe = $defaults;
        foreach ($safe as $token => $default) {
            $safe[$token] = self::valid($colors[$token] ?? null) ? strtolower($colors[$token]) : $default;
        }
        $variables = [];
        foreach ($safe as $token => $color) {
            $variables[] = '--public-'.$token.': '.$color;
        }
        foreach (self::shades() as $name => [$token, $original]) {
            $variables[] = '--public-'.$name.': '.self::shade($safe[$token], $defaults[$token], $original);
        }

        return implode('; ', $variables).';';
    }

    public static function contrast(string $foreground, string $background): float
    {
        $luminance = function (string $hex): float {
            $channels = [];
            for ($i = 1; $i < 7; $i += 2) {
                $channel = hexdec(substr($hex, $i, 2)) / 255;
                $channels[] = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }

            return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
        };
        $a = $luminance($foreground);
        $b = $luminance($background);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    public static function contrastChecks(array $colors): array
    {
        $pairs = [
            ['heading', 'background', 'Heading / Background', 3],
            ['text', 'background', 'Body Text / Background', 4.5],
            ['text-muted', 'background', 'Muted Text / Background', 4.5],
            ['text', 'surface', 'Body Text / Card', 4.5],
            ['heading', 'surface', 'Heading / Card', 3],
            ['text-muted', 'surface', 'Muted Text / Card', 4.5],
            ['text', 'surface-alt', 'Body Text / Alternative Surface', 4.5],
            ['heading', 'surface-alt', 'Heading / Alternative Surface', 3],
            ['header-text', 'header-bg', 'Navigation / Header', 4.5],
            ['footer-text', 'footer-bg', 'Footer Text / Footer', 4.5],
            ['footer-cta-text', 'footer-cta-bg', 'Newsletter Text / Newsletter', 4.5],
            ['button-text', 'button-bg', 'Button Text / Button', 4.5],
            ['button-text', 'primary-hover', 'Button Text / Hover', 4.5],
            ['button-text', 'primary', 'Badge Text / Primary', 4.5],
            ['on-secondary', 'secondary', 'Secondary Text / Secondary', 4.5],
            ['link', 'background', 'Link / Background', 4.5],
            ['link-hover', 'background', 'Link Hover / Background', 4.5],
            ['focus', 'surface', 'Focus / Surface', 3, false],
            ['input-border', 'surface', 'Input Border / Surface', 3, false],
            ['accent', 'background', 'Accent / Background (judul besar)', 3, false],
            ['footer-cta-accent', 'footer-cta-bg', 'Accent / Newsletter (judul besar)', 3],
        ];

        return array_map(function ($pair) use ($colors) {
            [$foreground, $background, $label, $target] = $pair;
            $ratio = self::contrast($colors[$foreground], $colors[$background]);
            $recommended = self::contrast('#000000', $colors[$background]) >= self::contrast('#ffffff', $colors[$background]) ? '#000000' : '#ffffff';

            return compact('foreground', 'background', 'label', 'target', 'ratio', 'recommended') + ['critical' => ($pair[4] ?? true) && $ratio < 1.5];
        }, $pairs);
    }

    public static function presets(): array
    {
        $classic = self::defaults();
        $preset = fn (array $overrides) => array_replace($classic, $overrides);

        return [
            'Besofton Classic' => $classic,
            'Modern Blue' => $preset(['footer-cta-accent' => '#93c5fd', 'primary' => '#1d4ed8', 'primary-hover' => '#1e40af', 'secondary' => '#0f172a', 'accent' => '#1d4ed8', 'background' => '#f1f5f9', 'surface' => '#ffffff', 'surface-alt' => '#e2e8f0', 'heading' => '#0f172a', 'text' => '#1e293b', 'text-muted' => '#475569', 'border' => '#cbd5e1', 'input-border' => '#64748b', 'focus' => '#1d4ed8', 'link' => '#1d4ed8', 'link-hover' => '#1e40af', 'eyebrow' => '#1d4ed8', 'header-bg' => '#ffffff', 'header-text' => '#0f172a', 'footer-bg' => '#e2e8f0', 'footer-text' => '#0f172a', 'footer-cta-bg' => '#0f172a', 'button-bg' => '#1d4ed8', 'button-text' => '#ffffff']),
            'Forest Green' => $preset(['footer-cta-accent' => '#86efac', 'primary' => '#166534', 'primary-hover' => '#14532d', 'secondary' => '#14261b', 'accent' => '#166534', 'background' => '#f0f7f1', 'surface' => '#ffffff', 'surface-alt' => '#e3eee5', 'heading' => '#14261b', 'text' => '#203d2b', 'text-muted' => '#4b6252', 'border' => '#c9d9cd', 'input-border' => '#66806d', 'focus' => '#166534', 'link' => '#166534', 'link-hover' => '#14532d', 'eyebrow' => '#166534', 'header-bg' => '#f8fcf8', 'header-text' => '#14261b', 'footer-bg' => '#e3eee5', 'footer-text' => '#14261b', 'footer-cta-bg' => '#14261b', 'button-bg' => '#166534', 'button-text' => '#ffffff']),
            'Red White' => $preset([
                'primary' => '#c62828',
                'primary-hover' => '#a91d1d',
                'secondary' => '#8e1b1b',
                'on-secondary' => '#ffffff',
                'accent' => '#e53935',
                'background' => '#fafafa',
                'surface' => '#ffffff',
                'surface-alt' => '#fff5f5',
                'border' => '#e5e5e5',
                'input-border' => '#a36a6a',
                'focus' => '#a91d1d',
                'heading' => '#171717',
                'text' => '#262626',
                'text-muted' => '#666666',
                'link' => '#a91d1d',
                'link-hover' => '#8e1b1b',
                'eyebrow' => '#a91d1d',
                'header-bg' => '#ffffff',
                'header-text' => '#171717',
                'footer-bg' => '#fff5f5',
                'footer-text' => '#262626',
                'footer-cta-bg' => '#8e1b1b',
                'footer-cta-text' => '#ffffff',
                'footer-cta-accent' => '#ffffff',
                'button-bg' => '#c62828',
                'button-text' => '#ffffff',
            ]),
        ];
    }
}
