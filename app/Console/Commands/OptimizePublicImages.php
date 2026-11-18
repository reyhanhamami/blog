<?php

namespace App\Console\Commands;

use App\Support\PublicImage;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

class OptimizePublicImages extends Command
{
    protected $signature = 'media:optimize-public';

    protected $description = 'Create optional responsive WebP copies of existing public uploads; preserve originals';

    public function handle(PublicImage $images): int
    {
        if (! function_exists('imagewebp')) {
            $this->warn('GD WebP unavailable. Original URLs remain in use.');

            return self::FAILURE;
        }
        $written = 0;
        if (is_dir(public_path('uploads'))) {
            foreach ((new Finder)->files()->in(public_path('uploads'))->exclude('optimized')->name('/\.(?:jpe?g|png|webp)$/i') as $file) {
                try {
                    $written += $images->optimize($file->getRealPath());
                } catch (\Throwable $exception) {
                    $this->warn($file->getRelativePathname().': '.$exception->getMessage());
                }
            }
        }
        $this->info($written.' responsive WebP copies created. Originals preserved.');

        return self::SUCCESS;
    }
}
