<?php

use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Page;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Video;
use App\Support\PermissionCatalog;
use App\Support\PublicTheme;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'public-theme-qa.sqlite')) {
    throw new RuntimeException('Theme QA requires the isolated public-theme-qa.sqlite database.');
}
Artisan::call('migrate', ['--force' => true]);
PermissionCatalog::seedDefaults();
User::firstOrCreate(['email' => 'theme-qa@example.test'], ['name' => 'Theme QA', 'role' => 'superadmin', 'password' => Hash::make('Theme-QA-local-only')]);
$category = Category::firstOrCreate(['slug' => 'theme-qa'], ['name' => 'Theme QA', 'is_active' => true]);
Post::firstOrCreate(['slug' => 'theme-qa-article'], ['title' => 'Panduan Digital Theme QA', 'excerpt' => 'Ringkasan artikel untuk pemeriksaan warna.', 'category_id' => $category->id, 'status' => 'published', 'published_at' => now()->subDay(), 'is_featured' => true, 'content' => '<h2>Langkah Pertama</h2><p>Konten QA untuk <a href="/cari">menguji tautan</a> dan keterbacaan artikel.</p><blockquote>Belajar setiap hari.</blockquote><table><tr><th>Judul</th><th>Nilai</th></tr><tr><td>Contoh</td><td>Data</td></tr></table><pre><code class="language-php">echo &quot;Hello&quot;;</code></pre>']);
Video::firstOrCreate(['slug' => 'theme-qa-video'], ['title' => 'Video QA', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->subDay()]);
LearningPath::firstOrCreate(['slug' => 'theme-qa-path'], ['title' => 'Jalur Belajar QA', 'status' => 'published']);
$course = Course::firstOrCreate(['slug' => 'theme-qa-course'], ['title' => 'Kelas QA', 'status' => 'published']);
$module = $course->modules()->firstOrCreate(['title' => 'Modul QA']);
$module->lessons()->firstOrCreate(['title' => 'Pelajaran QA'], ['type' => 'custom', 'content' => '<h2>Pelajaran</h2><p>Materi QA.</p>']);
$quiz = Quiz::firstOrCreate(['slug' => 'theme-qa-quiz'], ['title' => 'Kuis QA', 'status' => 'published', 'passing_score' => 70]);
$question = $quiz->questions()->firstOrCreate(['question' => 'Apa tujuan QA?'], ['type' => 'single_choice']);
$question->options()->firstOrCreate(['label' => 'Memeriksa kualitas'], ['is_correct' => true]);
$question->options()->firstOrCreate(['label' => 'Menghapus semua data'], ['is_correct' => false]);
Page::firstOrCreate(['slug' => 'about'], ['title' => 'Tentang QA', 'content' => '<p>Halaman statis QA.</p>', 'is_published' => true]);
PublicTheme::save(PublicTheme::defaults());
echo "Isolated public theme browser fixture ready.\n";
