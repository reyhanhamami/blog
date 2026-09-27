<?php

namespace Database\Seeders;

use App\Enums\CourseLessonType;
use App\Enums\LearningPathItemType;
use App\Enums\QuizQuestionType;
use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Media;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** Explicit, small sample dataset. Never called by the production core seeder. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        DB::transaction(function () {
            $users = $this->users();
            $authors = $this->authors($users);
            $categories = $this->categories();
            $tags = $this->tags();
            $topics = $this->topics();
            $this->media($users['editor']);
            $video = $this->video();
            $quiz = $this->quiz();
            $posts = $this->posts($users['author'], $authors['developer'], $categories, $tags, $topics, $quiz);
            $this->learningPath($posts['prompt'], $quiz, $video);
            $this->course($posts['prompt'], $quiz, $users['reader']);
            $this->engagement($posts['prompt'], $users);
            $this->redirect();
        });
    }

    private function users(): array
    {
        $defaults = [
            'editor' => ['Besofton Editor', 'editor@besofton.id'],
            'author' => ['Besofton Author', 'author@besofton.id'],
            'reader' => ['Demo Reader', 'reader@besofton.id'],
        ];
        $users = [];
        $hasLegacyPhone = Schema::hasColumn('users', 'mobile_phone');
        foreach ($defaults as $role => [$name, $email]) {
            $user = User::firstOrNew(['email' => $email]);
            if ($user->exists) {
                $users[$role] = $user;

                continue;
            }
            $values = [
                'name' => $name, 'role' => $role, 'password' => Hash::make('asddsa123'),
            ];
            if ($hasLegacyPhone) {
                $values['mobile_phone'] = '';
            }
            $user->forceFill($values)->save();
            $users[$role] = $user;
        }

        return $users;
    }

    private function authors(array $users): array
    {
        return [
            'team' => Author::firstOrCreate(['slug' => 'besofton-team'], [
                'user_id' => $users['editor']->id, 'name' => 'Besofton Team', 'job_title' => 'Editorial Team',
                'short_bio' => 'Tim Besofton yang membahas teknologi, pengembangan software, AI, dan strategi digital.',
            ]),
            'developer' => Author::firstOrCreate(['slug' => 'besofton-developer'], [
                'user_id' => $users['author']->id, 'name' => 'Besofton Developer', 'job_title' => 'Software Developer',
                'short_bio' => 'Menulis tutorial teknis tentang Laravel, AI-assisted development, DevOps, dan web development.',
            ]),
        ];
    }

    private function categories(): array
    {
        return [
            'web' => Category::withTrashed()->firstOrCreate(['slug' => 'website-development'], [
                'name' => 'Website & Development', 'description' => 'Panduan membangun dan mengembangkan website modern.', 'is_active' => true,
            ]),
            'ai' => Category::withTrashed()->firstOrCreate(['slug' => 'artificial-intelligence'], [
                'name' => 'Artificial Intelligence', 'description' => 'Penerapan AI yang praktis untuk pekerjaan digital.', 'is_active' => true,
            ]),
        ];
    }

    private function tags(): array
    {
        return [
            'laravel' => Tag::firstOrCreate(['slug' => 'laravel'], ['name' => 'Laravel']),
            'prompt' => Tag::firstOrCreate(['slug' => 'prompt-ai'], ['name' => 'Prompt AI']),
        ];
    }

    private function topics(): array
    {
        return [
            'ai' => Topic::firstOrCreate(['slug' => 'ai-for-developers'], [
                'name' => 'AI for Developers', 'description' => 'Praktik menggunakan AI untuk mempercepat pekerjaan developer tanpa melewatkan verifikasi.',
            ]),
            'laravel' => Topic::firstOrCreate(['slug' => 'laravel-development'], [
                'name' => 'Laravel Development', 'description' => 'Dasar dan praktik membangun aplikasi web dengan Laravel.',
            ]),
        ];
    }

    private function media(User $editor): void
    {
        // This is an existing brand asset, not a generated upload. Never create a broken media URL.
        $file = storage_path('app/public/logo.png');
        if (! is_file($file) || ! is_file(public_path('storage/logo.png'))) {
            return;
        }
        $image = @getimagesize($file);
        if (! $image || ! in_array($image['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return;
        }
        Media::firstOrCreate(['path' => 'storage/logo.png'], [
            'mime_type' => $image['mime'], 'size' => filesize($file), 'width' => $image[0], 'height' => $image[1],
            'alt_text' => 'Logo Besofton', 'caption' => 'Aset logo Besofton yang sudah tersedia.', 'uploaded_by' => $editor->id,
        ]);
    }

    private function video(): Video
    {
        // Public video from OpenAI and DeepLearning.AI; the ID is a verified real YouTube video.
        return Video::withTrashed()->firstOrCreate(['slug' => 'chatgpt-prompt-engineering-for-developers'], [
            'title' => 'ChatGPT Prompt Engineering for Developers', 'youtube_id' => 'H4YK_7MAckk',
            'description' => 'Materi pengantar prompt engineering untuk developer dari OpenAI dan DeepLearning.AI.',
            'status' => 'published', 'published_at' => now()->subDay(),
        ]);
    }

    private function quiz(): Quiz
    {
        $quiz = Quiz::withTrashed()->firstOrCreate(['slug' => 'quiz-dasar-prompt-ai-coding'], [
            'title' => 'Quiz Dasar Prompt AI untuk Coding',
            'description' => 'Uji pemahaman dasar tentang cara membuat prompt AI yang efektif untuk pengembangan software.',
            'passing_score' => 70, 'status' => 'published',
        ]);
        foreach ([
            [QuizQuestionType::SingleChoice, 'Apa elemen terpenting dalam prompt coding?', ['Konteks project', 'Warna editor', 'Nama laptop', 'Jam kerja'], [0], 'Konteks project membantu AI memahami kode dan tujuan perubahan.'],
            [QuizQuestionType::MultipleChoice, 'Informasi apa yang sebaiknya dimasukkan dalam prompt debugging?', ['Error message', 'Expected behavior', 'Relevant code', 'Wallpaper desktop'], [0, 1, 2], 'Pesan error, hasil yang diharapkan, dan potongan kode relevan mempersempit masalah.'],
            [QuizQuestionType::TrueFalse, 'Prompt coding yang baik sebaiknya menjelaskan batasan perubahan.', ['Benar', 'Salah'], [0], 'Batasan mencegah perubahan di luar cakupan yang diminta.'],
        ] as $index => [$type, $text, $options, $correct, $explanation]) {
            $question = $quiz->questions()->firstOrCreate(['sort_order' => $index + 1], [
                'question' => $text, 'type' => $type, 'points' => 1, 'explanation' => $explanation,
            ]);
            if ($question->wasRecentlyCreated) {
                foreach ($options as $position => $label) {
                    $question->options()->create(['label' => $label, 'is_correct' => in_array($position, $correct, true)]);
                }
            }
        }

        return $quiz;
    }

    private function posts(User $creator, Author $author, array $categories, array $tags, array $topics, Quiz $quiz): array
    {
        $samples = [
            'prompt' => [
                'slug' => 'cara-membuat-prompt-ai-untuk-coding', 'title' => 'Cara Membuat Prompt AI untuk Coding yang Efektif',
                'excerpt' => 'Panduan praktis menyusun prompt AI untuk membantu coding, debugging, code review, dan pengembangan aplikasi dengan hasil yang lebih terarah.',
                'content' => <<<'HTML'
<h2>Apa Itu Prompt AI untuk Coding?</h2>
<p>Prompt adalah instruksi yang menjelaskan pekerjaan yang ingin kita bantu dengan AI. Untuk coding, sertakan bahasa, framework, bagian aplikasi yang terkait, dan masalah yang ingin diselesaikan. Jangan menempelkan kredensial atau data pengguna asli.</p>
<h2>Struktur Prompt yang Efektif</h2>
<ul><li>Jelaskan konteks project dan versi framework.</li><li>Tentukan hasil yang diharapkan serta perilaku saat ini.</li><li>Sebutkan batasan perubahan, misalnya tidak mengubah skema database.</li><li>Minta langkah verifikasi atau test yang relevan.</li></ul>
<h2>Contoh Prompt</h2>
<pre><code class="language-text">Project: Laravel 13, fitur artikel.
Masalah: validasi slug menolak edit artikel sendiri.
Tujuan: perbaiki validasi agar slug lama tetap valid saat edit.
Batasan: jangan ubah migration atau route publik.
Verifikasi: jalankan test create dan edit artikel.</code></pre>
<h2>Kesalahan yang Sering Dilakukan</h2>
<p>Instruksi seperti “perbaiki semua bug” terlalu luas. Tinjau setiap usulan perubahan, terutama query, otorisasi, dan test. Gunakan AI sebagai pendamping kerja; keputusan akhir tetap berdasarkan kode dan hasil verifikasi.</p>
HTML,
                'category' => $categories['ai'], 'tag' => $tags['prompt'], 'topic' => $topics['ai'],
                'content_type' => 'tutorial', 'featured' => true, 'days_ago' => 2, 'views' => 3, 'quiz_id' => $quiz->id,
                'seo_title' => 'Cara Membuat Prompt AI untuk Coding | Besofton Insights',
                'seo_description' => 'Pelajari cara menyusun prompt AI untuk coding, debugging, review, dan pengembangan software secara lebih efektif.',
                'direct_answer' => 'Prompt AI untuk coding yang efektif harus menjelaskan konteks project, tujuan perubahan, batasan, dan hasil yang diharapkan.',
                'key_takeaways' => "Jelaskan konteks project.\nTentukan tujuan perubahan.\nSebutkan batasan.\nMinta AI melakukan verifikasi.",
            ],
            'laravel' => [
                'slug' => 'panduan-dasar-laravel-aplikasi-web-modern', 'title' => 'Panduan Dasar Laravel untuk Membangun Aplikasi Web Modern',
                'excerpt' => 'Mulai dari route, controller, Blade, Eloquent, hingga test untuk membangun aplikasi web Laravel yang mudah dipelihara.',
                'content' => <<<'HTML'
<h2>Memahami Alur Request Laravel</h2>
<p>Request masuk melalui route, diteruskan ke controller, lalu menghasilkan response. Pisahkan aturan bisnis dari tampilan agar perubahan fitur lebih mudah diuji.</p>
<h2>Mengelola Data dengan Eloquent</h2>
<p>Gunakan model dan relasi untuk membaca data terkait. Saat menampilkan daftar artikel, eager load penulis dan kategori untuk menghindari query berulang.</p>
<h2>Membangun Tampilan dan Verifikasi</h2>
<p>Blade merender HTML di server sehingga konten utama dapat dibaca tanpa JavaScript. Tambahkan validasi input, policy untuk otorisasi, dan test yang mencakup hasil sukses maupun akses terlarang.</p>
HTML,
                'category' => $categories['web'], 'tag' => $tags['laravel'], 'topic' => $topics['laravel'],
                'content_type' => 'guide', 'featured' => false, 'days_ago' => 5, 'views' => 1, 'quiz_id' => null,
                'seo_title' => 'Panduan Dasar Laravel untuk Aplikasi Web | Besofton Insights',
                'seo_description' => 'Pelajari alur request, Eloquent, Blade, validasi, dan test sebagai fondasi aplikasi web Laravel modern.',
                'direct_answer' => null, 'key_takeaways' => null,
            ],
        ];
        $posts = [];
        foreach ($samples as $key => $sample) {
            $content = app(HtmlSanitizer::class)->clean($sample['content']);
            $post = Post::withTrashed()->firstOrCreate(['slug' => $sample['slug']], [
                'title' => $sample['title'], 'excerpt' => $sample['excerpt'], 'content' => $content,
                'content_plain' => trim(strip_tags($content)), 'category_id' => $sample['category']->id,
                'author_id' => $author->id, 'created_by' => $creator->id, 'updated_by' => $creator->id,
                'content_type' => $sample['content_type'], 'status' => 'published', 'difficulty' => 'beginner',
                'is_featured' => $sample['featured'], 'noindex' => false, 'allow_comments' => true,
                'quiz_id' => $sample['quiz_id'], 'seo_title' => $sample['seo_title'],
                'seo_description' => $sample['seo_description'], 'og_title' => $sample['seo_title'],
                'og_description' => $sample['seo_description'], 'direct_answer' => $sample['direct_answer'],
                'key_takeaways' => $sample['key_takeaways'], 'views' => $sample['views'],
                'published_at' => now()->subDays($sample['days_ago']),
                'created_at' => now()->subDays($sample['days_ago'] + 1),
                'updated_at' => now()->subDays($sample['days_ago']),
            ]);
            if ($post->wasRecentlyCreated) {
                $post->tags()->syncWithoutDetaching([$sample['tag']->id]);
                $post->topics()->syncWithoutDetaching([$sample['topic']->id]);
            }
            $posts[$key] = $post;
        }
        if ($posts['prompt']->wasRecentlyCreated && $posts['laravel']->wasRecentlyCreated) {
            $posts['prompt']->relatedPosts()->syncWithoutDetaching([$posts['laravel']->id]);
            $posts['laravel']->relatedPosts()->syncWithoutDetaching([$posts['prompt']->id]);
        }
        $posts['prompt']->sources()->firstOrCreate(['url' => 'https://laravel.com/docs'], [
            'title' => 'Laravel Documentation', 'publisher' => 'Laravel', 'sort_order' => 1,
        ]);

        return $posts;
    }

    private function learningPath(Post $post, Quiz $quiz, Video $video): void
    {
        $path = LearningPath::withTrashed()->firstOrCreate(['slug' => 'ai-for-developer-dasar'], [
            'title' => 'AI for Developer — Dasar', 'description' => 'Belajar menyusun prompt coding lalu uji pemahaman melalui kuis.',
            'status' => 'published',
        ]);
        if (! $path->wasRecentlyCreated) {
            return;
        }
        $path->items()->create(['type' => LearningPathItemType::Article, 'post_id' => $post->id, 'sort_order' => 1]);
        $path->items()->create(['type' => LearningPathItemType::Video, 'video_id' => $video->id, 'sort_order' => 2]);
        $path->items()->create(['type' => LearningPathItemType::Quiz, 'quiz_id' => $quiz->id, 'sort_order' => 3]);
    }

    private function course(Post $post, Quiz $quiz, User $reader): void
    {
        $course = Course::withTrashed()->firstOrCreate(['slug' => 'fundamental-ai-assisted-development'], [
            'title' => 'Fundamental AI-Assisted Development', 'description' => 'Dasar penggunaan AI untuk membantu pekerjaan developer secara terarah.',
            'status' => 'published',
        ]);
        if ($course->wasRecentlyCreated) {
            $module = $course->modules()->create(['title' => 'Dasar Prompt Engineering', 'sort_order' => 1]);
            $module->lessons()->create(['title' => $post->title, 'type' => CourseLessonType::Article, 'post_id' => $post->id, 'sort_order' => 1]);
            $module->lessons()->create(['title' => $quiz->title, 'type' => CourseLessonType::Quiz, 'quiz_id' => $quiz->id, 'requires_pass' => true, 'sort_order' => 2]);
        }
        $firstLesson = $course->modules()->orderBy('sort_order')->first()?->lessons()->orderBy('sort_order')->first();
        if (! $firstLesson) {
            return;
        }
        DB::table('course_progress')->insertOrIgnore([
            'user_id' => $reader->id, 'course_lesson_id' => $firstLesson->id, 'completed_at' => now()->subDay(),
        ]);
        DB::table('course_activity')->insertOrIgnore([
            'user_id' => $reader->id, 'course_id' => $course->id, 'last_lesson_id' => $firstLesson->id,
            'last_opened_at' => now()->subDay(),
        ]);
    }

    private function engagement(Post $post, array $users): void
    {
        $question = Question::firstOrCreate(['post_id' => $post->id, 'user_id' => $users['reader']->id], [
            'name' => $users['reader']->name, 'email' => $users['reader']->email,
            'body' => 'Apakah prompt yang sama bisa digunakan untuk Laravel dan framework lain?', 'status' => 'approved',
        ]);
        $question->answers()->firstOrCreate(['user_id' => $users['editor']->id], [
            'body' => 'Bisa, tetapi sebaiknya konteks framework, versi, struktur project, dan batasan perubahan disesuaikan agar hasil AI lebih relevan.',
        ]);
        foreach (['helpful' => true, 'not-helpful' => false] as $key => $helpful) {
            DB::table('article_feedback')->insertOrIgnore([
                'post_id' => $post->id, 'visitor_hash' => hash('sha256', 'besofton-demo-feedback:'.$key),
                'helpful' => $helpful, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('bookmarks')->insertOrIgnore([
            'user_id' => $users['reader']->id, 'post_id' => $post->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        NewsletterSubscriber::firstOrCreate(['email' => 'demo.reader@besofton.id'], [
            'status' => NewsletterSubscriber::SUBSCRIBED, 'subscribed_at' => now()->subDay(),
        ]);
    }

    private function redirect(): void
    {
        DB::table('redirects')->insertOrIgnore([
            'from_path' => '/panduan-prompt-ai', 'to_path' => '/cara-membuat-prompt-ai-untuk-coding',
            'status_code' => 301, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
