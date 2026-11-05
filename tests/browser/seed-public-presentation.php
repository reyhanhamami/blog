<?php

use App\Models\Post;
use App\Models\Setting;
use Illuminate\Support\Facades\File;

// The shared fixture guards the isolated SQLite filename before any writes.
require __DIR__.'/seed-public-theme.php';
File::ensureDirectoryExists(public_path('uploads'));
$images = ['landscape' => [1600, 900], 'square' => [900, 900], 'portrait' => [600, 900], 'wide' => [1800, 600], 'logo' => [200, 120]];
foreach ($images as $name => [$width, $height]) {
    $image = imagecreatetruecolor($width, $height);
    $background = imagecolorallocate($image, 242, 234, 218);
    imagefill($image, 0, 0, $background);
    foreach ([[0, 0, [210, 65, 65]], [$width - 60, 0, [45, 135, 80]], [0, $height - 60, [50, 95, 180]], [$width - 60, $height - 60, [210, 160, 40]]] as [$x, $y, $color]) {
        imagefilledrectangle($image, $x, $y, $x + 59, $y + 59, imagecolorallocate($image, ...$color));
    }
    imagestring($image, 5, (int) ($width / 2 - 90), (int) ($height / 2), strtoupper($name).' '.$width.'x'.$height, imagecolorallocate($image, 105, 65, 35));
    imagepng($image, public_path('uploads/qa-presentation-'.$name.'.png'));
    imagedestroy($image);
}
foreach (['landscape', 'square', 'portrait'] as $index => $name) {
    Post::updateOrCreate(['slug' => 'presentation-image-'.$name], [
        'title' => 'Gambar '.ucfirst($name).' untuk Pemeriksaan Artikel Unggulan',
        'excerpt' => 'Fixture pengujian visual. Gambar utama harus terlihat utuh dengan empat sudut tetap tampak.',
        'status' => 'published', 'published_at' => now()->subMinutes($index + 1),
        'is_featured' => true, 'featured_image' => '/uploads/qa-presentation-'.$name.'.png',
        'featured_image_alt' => 'QA '.$name.' with four colored corners', 'content' => '<p>Fixture gambar.</p>',
    ]);
}
foreach (['long' => 32, 'short' => 5] as $name => $count) {
    $content = '';
    for ($index = 1; $index <= $count; $index++) {
        $content .= '<h2>Bagian '.$index.'</h2><p>'.str_repeat('Materi pengujian sidebar mengikuti artikel dengan nyaman dan tidak menutupi footer. ', 24).'</p>';
    }
    Post::updateOrCreate(['slug' => 'presentation-sidebar-'.$name], ['title' => 'Pemeriksaan Sidebar '.ucfirst($name), 'status' => 'published', 'published_at' => now()->subDay(), 'is_featured' => false, 'content' => $content]);
}
// QA-only branding avoids a missing /storage/logo.png and exercises a tall header.
Setting::putValue('logo_url', 'http://127.0.0.1:8767/uploads/qa-presentation-logo.png');
echo "Presentation fixtures ready: unmodified source files plus long and short TOCs.\n";
