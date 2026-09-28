<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Quiz;

test('public discovery and account pages share the editorial shell without a language selector', function () {
    foreach ([route('home'), route('search'), route('categories.index'), route('login'), route('register'), route('password.request')] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toContain('class="public-site-header"')
            ->toContain('class="public-site-footer"')
            ->toContain('class="public-brand"')
            ->not->toContain('class="public-language"');
    }

    $this->get('/halaman-tidak-ada-untuk-redesign')->assertNotFound()->assertSee('Sepertinya Anda tersesat.');
});

test('article retains server rendered content and editorial reading controls', function () {
    $category = Category::create(['name' => 'Teknologi', 'slug' => 'teknologi', 'is_active' => true]);
    $quiz = Quiz::create(['title' => 'Kuis Teknologi', 'slug' => 'kuis-teknologi', 'status' => 'published', 'passing_score' => 70]);
    $post = Post::create([
        'title' => 'Panduan Teknologi', 'slug' => 'panduan-teknologi', 'status' => 'published',
        'published_at' => now()->subHour(), 'category_id' => $category->id, 'quiz_id' => $quiz->id,
        'excerpt' => 'Panduan praktis untuk pembaca.', 'content' => '<h2>Langkah pertama</h2><p>Isi artikel yang terlihat tanpa JavaScript.</p>',
        'direct_answer' => 'Mulai dari tujuan.', 'key_takeaways' => "Tentukan tujuan\nVerifikasi hasil",
    ]);

    $html = $this->get(route('post.show', $post->slug))->assertOk()->getContent();
    expect($html)->toContain('<h1>Panduan Teknologi</h1>')
        ->toContain('Isi artikel yang terlihat tanpa JavaScript.')
        ->toContain('class="article-layout"')
        ->toContain('id="article-toc"')
        ->toContain('Jawaban singkat')
        ->toContain('Inti Pembahasan')
        ->toContain('Mulai Quiz')
        ->toContain(route('quiz.show', $quiz))
        ->toContain('Apakah artikel ini membantu?')
        ->toContain('Tanya jawab')
        ->toContain('Ide baru, langsung ke inbox Anda.')
        ->toContain('application/ld+json')
        ->not->toContain('text-indigo-');
});

test('archive and quiz pages render the same public components', function () {
    $category = Category::create(['name' => 'AI', 'slug' => 'ai', 'is_active' => true]);
    $post = Post::create(['title' => 'Belajar AI', 'slug' => 'belajar-ai', 'status' => 'published', 'published_at' => now()->subHour(), 'category_id' => $category->id, 'content' => '<p>Materi AI</p>']);
    $quiz = Quiz::create(['title' => 'Kuis AI', 'slug' => 'kuis-ai', 'status' => 'published', 'passing_score' => 70]);

    $this->get(route('category.show', $category))->assertOk()->assertSee('public-card-grid')->assertSee($post->title);
    $this->get(route('search', ['q' => 'Belajar AI']))->assertOk()->assertSee('public-search-form')->assertSee($post->title);
    $this->get(route('quiz.show', $quiz))->assertOk()->assertSee('public-page-hero')->assertSee('public-quiz-player');
});
