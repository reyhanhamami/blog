<?php

use App\Models\Post;
use App\Models\Setting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'featured-slider-qa.sqlite')) {
    throw new RuntimeException('Slider QA requires its isolated SQLite database.');
}
Artisan::call('migrate', ['--force' => true]);
if (! is_file(public_path('uploads/qa-featured-slider.png'))) {
    File::ensureDirectoryExists(public_path('uploads'));
    $image = imagecreatetruecolor(800, 450);
    imagefill($image, 0, 0, imagecolorallocate($image, 230, 210, 170));
    imagefilledrectangle($image, 30, 30, 770, 420, imagecolorallocate($image, 60, 100, 130));
    imagepng($image, public_path('uploads/qa-featured-slider.png'));
    imagedestroy($image);
}
$count = (int) ($argv[1] ?? 3);
if (! in_array($count, [1, 2, 3], true)) {
    throw new RuntimeException('Expected 1, 2 or 3 featured posts.');
}
foreach (range(1, 3) as $number) {
    Post::updateOrCreate(['slug' => 'slider-qa-'.$number], [
        'title' => 'Artikel unggulan '.$number,
        'excerpt' => 'Ringkasan artikel unggulan '.$number.' untuk pengujian klik dan navigasi.',
        'content' => '<h2>Artikel '.$number.'</h2><p>Konten fixture slider lokal.</p>',
        'content_plain' => 'Konten fixture slider lokal.',
        'status' => 'published', 'published_at' => now()->subDays($number),
        'is_featured' => $number <= $count,
        'featured_image' => '/uploads/qa-featured-slider.png',
    ]);
}
Setting::putValue('logo_url', '/uploads/qa-featured-slider.png');
echo $count." featured articles ready in isolated SQLite.\n";
