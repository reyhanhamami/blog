<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class PublicImage
{
    public const WIDTHS = [160, 320, 640, 960, 1280];

    public static function localPath(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url(config('app.url'), PHP_URL_HOST)) {
            return null;
        }
        $relative = ltrim(rawurldecode(parse_url($url, PHP_URL_PATH) ?: ''), '/');
        if (! str_starts_with($relative, 'uploads/') || str_contains($relative, '..') || str_contains($relative, '\\')) {
            return null;
        }
        $path = realpath(public_path($relative));
        $root = realpath(public_path('uploads'));

        return $path && $root && str_starts_with($path, $root.DIRECTORY_SEPARATOR) && is_file($path) ? $path : null;
    }

    public static function variant(string $path, int $width): string
    {
        // New files when the source changes; old URLs and originals stay valid.
        $normalized = str_replace('\\', '/', realpath($path) ?: $path);
        $root = str_replace('\\', '/', realpath(public_path()) ?: public_path());
        $key = hash('sha256', str_replace($root, '', $normalized).'|'.filemtime($path).'|'.filesize($path).'|webp-v1');

        return 'uploads/optimized/'.$key.'-'.$width.'.webp';
    }

    public static function sources(string $url, string $sizes, int $maximum = 1280): HtmlString
    {
        $variants = [];
        if ($path = self::localPath($url)) {
            foreach (self::WIDTHS as $width) {
                if ($width <= $maximum && is_file(public_path($variant = self::variant($path, $width)))) {
                    $variants[$width] = asset($variant);
                }
            }
        }
        // Keep the original URL as the compatible fallback; modern browsers select srcset.
        $attributes = 'src="'.e($url).'"';
        if ($variants) {
            $set = [];
            foreach ($variants as $width => $variant) {
                $set[] = $variant.' '.$width.'w';
            }
            $attributes .= ' srcset="'.e(implode(', ', $set)).'" sizes="'.e($sizes).'"';
        }

        return new HtmlString($attributes);
    }

    public static function dimensions(string $url, ?int $width = null, ?int $height = null): HtmlString
    {
        $dimensions = ($path = self::localPath($url)) ? @getimagesize($path) : false;

        return new HtmlString($dimensions ? 'width="'.$dimensions[0].'" height="'.$dimensions[1].'"' : ($width && $height ? 'width="'.$width.'" height="'.$height.'"' : ''));
    }

    public function optimize(string $path): int
    {
        if (! function_exists('imagewebp') || ! function_exists('imagecreatefromstring')) {
            return 0;
        }
        $dimensions = @getimagesize($path);
        // Bound decompression memory on shared hosting; never flatten animated GIFs.
        if (! $dimensions || ! in_array($dimensions['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $dimensions[0] * $dimensions[1] > 8_000_000) {
            return 0;
        }
        if ($dimensions['mime'] === 'image/webp' && str_contains(file_get_contents($path, false, null, 0, 4096), 'ANIM')) {
            return 0;
        }
        if ($dimensions['mime'] === 'image/jpeg' && function_exists('exif_read_data') && ((@exif_read_data($path)['Orientation'] ?? 1) !== 1)) {
            return 0;
        }
        $limit = ini_get('memory_limit');
        if ($limit !== '-1') {
            $bytes = (int) $limit * match (strtolower(substr($limit, -1))) {'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1};
            if (memory_get_usage(true) + $dimensions[0] * $dimensions[1] * 12 + 16 * 1024 ** 2 > $bytes) {
                return 0;
            }
        }
        $source = null;
        $written = 0;
        try {
            foreach (self::WIDTHS as $width) {
                if ($width > $dimensions[0]) {
                    continue;
                }
                $destination = public_path(self::variant($path, $width));
                if (is_file($destination)) {
                    continue;
                }
                $source ??= @imagecreatefromstring(file_get_contents($path));
                if (! $source) {
                    break;
                }
                $height = max(1, (int) round($dimensions[1] * $width / $dimensions[0]));
                $image = imagecreatetruecolor($width, $height);
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);
                if (! is_dir(dirname($destination))) {
                    \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($destination));
                }
                $temporary = tempnam(dirname($destination), 'webp-');
                try {
                    if (imagewebp($image, $temporary, 82) && filesize($temporary) > 0 && filesize($temporary) < filesize($path)) {
                        if (rename($temporary, $destination)) {
                            $written++;
                        }
                    }
                } finally {
                    imagedestroy($image);
                    if (is_file($temporary)) {
                        unlink($temporary);
                    }
                }
            }
        } finally {
            if ($source) {
                imagedestroy($source);
            }
        }

        return $written;
    }
}
