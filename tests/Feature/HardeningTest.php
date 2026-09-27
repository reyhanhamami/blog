<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\UploadedFile;

function qaUser(string $role = 'superadmin'): User
{
    return User::factory()->create(['role' => $role]);
}

function qaPost(array $attributes = []): Post
{
    return Post::create(array_merge([
        'title' => 'Artikel QA', 'slug' => 'artikel-qa', 'status' => 'published',
        'published_at' => now()->subMinute(), 'content' => '<p>Isi artikel QA.</p>',
        'content_plain' => 'Isi artikel QA.',
    ], $attributes));
}

test('article form persists all critical fields and renders server HTML after publication', function () {
    $admin = qaUser();
    $category = Category::create(['name' => 'Laravel', 'slug' => 'laravel']);
    $tag = Tag::create(['name' => 'PHP', 'slug' => 'php']);
    $topic = Topic::create(['name' => 'Backend', 'slug' => 'backend']);
    $author = Author::create(['name' => 'Dina', 'slug' => 'dina']);
    $this->actingAs($admin)->get(route('admin.posts.create'))->assertOk()->assertSee('data-post-form', false);
    $this->actingAs($admin)->post(route('admin.posts.store'), [
        'title' => 'Panduan QA', 'slug' => 'panduan-qa', 'excerpt' => 'Ringkasan QA',
        'content' => '<h2>Langkah pertama</h2><p>Isi utama.</p><pre><code class="language-php">echo 1;</code></pre><p>[youtube:abcdefghijk]</p>',
        'category_id' => $category->id, 'author_id' => $author->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
        'featured_image' => 'https://example.test/cover.jpg', 'featured_image_alt' => 'Sampul QA',
        'direct_answer' => 'Jawaban langsung', 'key_takeaways' => "Poin satu\nPoin dua",
        'seo_title' => 'Judul SEO QA', 'seo_description' => 'Deskripsi SEO QA',
        'og_title' => 'Judul OG QA', 'og_description' => 'Deskripsi OG QA', 'og_image' => 'https://example.test/og.jpg',
        'content_type' => 'tutorial', 'status' => 'draft',
    ])->assertRedirect();
    $post = Post::where('slug', 'panduan-qa')->firstOrFail();
    expect($post->category_id)->toBe($category->id);
    expect($post->tags->modelKeys())->toBe([$tag->id]);
    expect($post->topics->modelKeys())->toBe([$topic->id]);
    $this->actingAs($admin)->get(route('admin.posts.edit', $post))->assertOk()->assertSee('Judul SEO QA')->assertSee('Sampul QA')->assertSee('abcdefghijk');
    $this->get('/panduan-qa')->assertNotFound();
    $this->actingAs($admin)->get(route('admin.posts.preview', $post))->assertOk()->assertSee('noindex,nofollow');
    $this->actingAs($admin)->post(route('admin.posts.sources.store', $post), ['title' => 'Dokumentasi resmi', 'url' => 'https://example.test/docs', 'publisher' => 'Penerbit', 'sort_order' => 1])->assertRedirect();
    $this->actingAs($admin)->patch(route('admin.posts.update', $post), [
        'title' => $post->title, 'slug' => $post->slug, 'excerpt' => $post->excerpt, 'content' => $post->content,
        'category_id' => $category->id, 'author_id' => $author->id, 'tags' => [$tag->id], 'topics' => [$topic->id],
        'featured_image' => $post->featured_image, 'featured_image_alt' => $post->featured_image_alt,
        'direct_answer' => $post->direct_answer, 'key_takeaways' => $post->key_takeaways,
        'seo_title' => $post->seo_title, 'seo_description' => $post->seo_description,
        'og_title' => $post->og_title, 'og_description' => $post->og_description, 'og_image' => $post->og_image,
        'content_type' => 'tutorial', 'status' => 'published',
    ])->assertRedirect();
    $html = $this->get('/panduan-qa')->assertOk()->getContent();
    expect($html)->toContain('<h1', 'Isi utama.', 'language-php', 'data-youtube="abcdefghijk"', 'Dokumentasi resmi', 'Poin satu', 'Dina');
    expect(substr_count($html, '<link rel="canonical"'))->toBe(1);
    expect(preg_match_all('/<h1\b/', $html))->toBe(1);
    expect($html)->toContain('<title>Judul SEO QA | Besofton Insights</title>', 'property="og:image" content="https://example.test/og.jpg"');
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    $schemas = array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
    $article = collect($schemas)->firstWhere('@type', 'Article');
    expect($article['headline'])->toBe('Panduan QA');
    expect($article['image'])->toBe('https://example.test/cover.jpg');
    expect($article['author']['name'])->toBe('Dina');
    expect(collect($schemas)->pluck('@type'))->toContain('BreadcrumbList');
});

test('sanitizer removes scripts event handlers dangerous URLs and arbitrary iframes', function () {
    $html = '<h2>Judul</h2><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a><iframe src="https://evil.example"></iframe><pre><code class="language-php">echo 1;</code></pre>';
    $safe = app(HtmlSanitizer::class)->clean($html);
    expect($safe)->toContain('<h2>Judul</h2>', 'language-php');
    expect($safe)->not->toContain('<script', 'onerror', 'javascript:', '<iframe', 'src="x"');
});

test('sitemap excludes draft future noindex soft deleted and preview URLs', function () {
    qaPost();
    qaPost(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'published_at' => null]);
    qaPost(['title' => 'Future', 'slug' => 'future', 'published_at' => now()->addDay()]);
    qaPost(['title' => 'Scheduled', 'slug' => 'scheduled', 'status' => 'scheduled', 'published_at' => null, 'scheduled_at' => now()->addDay()]);
    qaPost(['title' => 'Noindex', 'slug' => 'noindex', 'noindex' => true]);
    $deleted = qaPost(['title' => 'Deleted', 'slug' => 'deleted']);
    $deleted->delete();
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();
    expect($xml)->toContain('/artikel-qa');
    expect($xml)->not->toContain('/draft', '/future', '/scheduled', '/noindex', '/deleted', '/admin', '/preview');
});

test('robots disallows staging and advertises sitemap only in production', function () {
    config()->set('app.env', 'staging');
    $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
    config()->set('app.env', 'production');
    $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
});
test('role authorization is enforced on endpoints', function () {
    $post = qaPost();
    $guest = $this->get('/admin');
    $guest->assertRedirect('/login');
    $reader = qaUser('reader');
    $this->actingAs($reader)->get('/admin')->assertForbidden();
    $this->actingAs($reader)->post(route('admin.posts.bulk'), ['ids' => [$post->id], 'action' => 'trash'])->assertForbidden();
    $author = qaUser('author');
    $this->actingAs($author)->get(route('admin.posts.edit', $post))->assertForbidden();
    $this->actingAs($author)->patch(route('admin.users.update', $reader), ['name' => 'Changed', 'email' => $reader->email, 'role' => 'admin'])->assertForbidden();
    $editor = qaUser('editor');
    $this->actingAs($editor)->get(route('admin.posts.edit', $post))->assertOk();
    $this->actingAs($editor)->get(route('admin.users.index'))->assertForbidden();
    $admin = qaUser('admin');
    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    $superadmin = qaUser();
    $this->actingAs($superadmin)->get(route('admin.settings.edit'))->assertOk();
});

test('menu and redirect endpoints reject external or looping local paths', function () {
    $admin = qaUser();
    $this->actingAs($admin)->get(route('admin.menus.index'))->assertOk();
    $menu = Menu::where('location', 'header')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), ['label' => 'Unsafe', 'url' => '/\\evil.example', 'sort_order' => 1])->assertSessionHasErrors('url');
    $this->actingAs($admin)->post(route('admin.redirects.store'), ['from_path' => '/one', 'to_path' => '/\\evil.example', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $this->actingAs($admin)->post(route('admin.redirects.store'), ['from_path' => '/one', 'to_path' => '/two', 'status_code' => 301])->assertRedirect();
    $this->actingAs($admin)->post(route('admin.redirects.store'), ['from_path' => '/two', 'to_path' => '/one', 'status_code' => 301])->assertSessionHasErrors('to_path');
    $this->get('/one')->assertRedirect('/two');
});

test('upload validation rejects executable names disguised images html and oversize files', function () {
    $admin = qaUser();
    foreach (['shell.php', 'shell.phtml', 'shell.phar'] as $name) {
        $this->actingAs($admin)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create($name, 2, 'application/x-php'), 'alt_text' => 'Unsafe'])->assertSessionHasErrors('file');
    }
    $this->actingAs($admin)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->image('shell.php.jpg', 32, 32), 'alt_text' => 'Unsafe'])->assertSessionHasErrors('file');
    $this->actingAs($admin)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->createWithContent('fake.jpg', '<html>not an image</html>'), 'alt_text' => 'Unsafe'])->assertSessionHasErrors('file');
    $this->actingAs($admin)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create('huge.jpg', 5121, 'image/jpeg'), 'alt_text' => 'Unsafe'])->assertSessionHasErrors('file');
    $this->assertDatabaseCount('media', 0);
});

test('quiz scoring ignores client supplied score and correctness flags', function () {
    $quiz = Quiz::create(['title' => 'Keamanan', 'slug' => 'keamanan', 'status' => 'published', 'passing_score' => 80]);
    $question = $quiz->questions()->create(['question' => 'Pilih benar', 'sort_order' => 1]);
    $wrong = $question->options()->create(['label' => 'Salah', 'is_correct' => false]);
    $correct = $question->options()->create(['label' => 'Benar', 'is_correct' => true]);
    $this->post(route('quiz.submit', $quiz), ['answers' => [$question->id => $wrong->id], 'score' => 100, 'is_correct' => true, 'passing_score' => 0])->assertSessionHas('quiz_result');
    $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 0, 'correct_count' => 0]);
    $this->post(route('quiz.submit', $quiz), ['answers' => [$question->id => $correct->id], 'score' => 0])->assertSessionHas('quiz_result');
    $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 100, 'correct_count' => 1]);
});

test('bookmarks and lesson progress remain isolated by reader', function () {
    $first = qaUser('reader');
    $second = qaUser('reader');
    $post = qaPost();
    $course = Course::create(['title' => 'Kelas QA', 'slug' => 'kelas-qa', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Dasar', 'sort_order' => 1]);
    $lesson = $module->lessons()->create(['title' => 'Pelajaran', 'sort_order' => 1]);
    $this->actingAs($first)->post(route('reader.bookmark', $post))->assertRedirect();
    $this->actingAs($first)->post(route('reader.lesson.complete', [$course, $lesson]))->assertRedirect();
    $this->actingAs($second)->get(route('reader.account'))->assertOk()->assertDontSee('Artikel QA');
    $this->actingAs($second)->get(route('course.lesson', [$course, $lesson]))->assertOk()->assertSee('0 / 1 selesai');
    $this->assertDatabaseMissing('bookmarks', ['user_id' => $second->id, 'post_id' => $post->id]);
    $this->assertDatabaseMissing('course_progress', ['user_id' => $second->id, 'course_lesson_id' => $lesson->id]);
});

test('quiz passing score remains within 0 to 100', function () {
    $admin = qaUser();
    $this->actingAs($admin)->post(route('admin.quizzes.store'), ['title' => 'Tidak valid', 'status' => 'published', 'passing_score' => 101])->assertSessionHasErrors('passing_score');
});
test('referenced media cannot be deleted and reports an error toast', function () {
    $admin = qaUser();
    $media = Media::create(['path' => 'uploads/example.jpg', 'mime_type' => 'image/jpeg', 'size' => 123, 'alt_text' => 'Example', 'uploaded_by' => $admin->id]);
    qaPost(['featured_image' => '/uploads/example.jpg']);
    $this->actingAs($admin)->delete(route('admin.media.destroy', $media))->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('media', ['id' => $media->id]);
});

test('article with no image omits empty og image and staging has noindex meta', function () {
    qaPost(['excerpt' => 'Ringkasan']);
    config()->set('app.env', 'staging');
    $html = $this->get('/artikel-qa')->assertOk()->getContent();
    expect($html)->toContain('<meta name="robots" content="noindex,nofollow">');
    expect($html)->not->toContain('property="og:image" content=""');
});

test('author and video pages emit valid matching structured data', function () {
    $author = Author::create(['name' => 'Sari', 'slug' => 'sari', 'short_bio' => 'Penulis teknologi']);
    $profile = $this->get(route('author.show', $author))->assertOk()->getContent();
    expect($profile)->toContain('ProfilePage', 'Penulis teknologi');
    $video = Video::create(['title' => 'Tutorial Video', 'slug' => 'tutorial-video', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $html = $this->get(route('video.show', $video))->assertOk()->getContent();
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    $schemas = array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $matches[1]);
    $schema = collect($schemas)->firstWhere('@type', 'VideoObject');
    expect($schema['name'])->toBe('Tutorial Video');
    expect($schema['embedUrl'])->toContain('abcdefghijk');
});

test('public search type filters show only matching content types', function () {
    qaPost();
    Video::create(['title' => 'Artikel QA Video', 'slug' => 'artikel-qa-video', 'youtube_id' => 'abcdefghijk', 'status' => 'published', 'published_at' => now()->subMinute()]);
    Course::create(['title' => 'Artikel QA Course', 'slug' => 'artikel-qa-course', 'status' => 'published']);
    $this->get('/cari?q=Artikel+QA&type=videos')->assertOk()->assertSee('Artikel QA Video')->assertDontSee('Artikel QA Course');
    $this->get('/cari?q=Artikel+QA&type=courses')->assertOk()->assertSee('Artikel QA Course')->assertDontSee('Artikel QA Video');
});
test('catalog module uses one page form for create and edit and persists lifecycle', function (string $module, string $model, array $fields, bool $softDeletes) {
    $admin = qaUser();
    $this->actingAs($admin)->get(route('admin.'.$module.'.create'))->assertOk()->assertViewIs('admin.catalog.form');
    $this->actingAs($admin)->post(route('admin.'.$module.'.store'), $fields)->assertRedirect();
    $item = $model::firstOrFail();
    $this->actingAs($admin)->get(route('admin.'.$module.'.edit', $item->id))->assertOk()->assertViewIs('admin.catalog.form');
    $field = isset($fields['name']) ? 'name' : 'title';
    $updated = array_merge($fields, [$field => $fields[$field].' Baru']);
    $this->actingAs($admin)->patch(route('admin.'.$module.'.update', $item->id), $updated)->assertRedirect();
    expect($item->fresh()->$field)->toBe($updated[$field]);
    $this->actingAs($admin)->delete(route('admin.'.$module.'.destroy', $item->id))->assertRedirect();
    expect($model::find($item->id))->toBeNull();
    if ($softDeletes) {
        $this->actingAs($admin)->post(route('admin.'.$module.'.restore', $item->id))->assertRedirect();
        expect($model::find($item->id))->not->toBeNull();
    }
})->with([
    'categories' => ['categories', Category::class, ['name' => 'Kategori QA', 'slug' => '', 'sort_order' => 1], true],
    'tags' => ['tags', Tag::class, ['name' => 'Tag QA', 'slug' => ''], false],
    'topics' => ['topics', Topic::class, ['name' => 'Topik QA', 'slug' => ''], false],
    'authors' => ['authors', Author::class, ['name' => 'Penulis QA', 'slug' => ''], false],
    'videos' => ['videos', Video::class, ['title' => 'Video QA', 'slug' => '', 'youtube_id' => 'abcdefghijk', 'status' => 'published'], true],
    'quizzes' => ['quizzes', Quiz::class, ['title' => 'Kuis QA', 'slug' => '', 'passing_score' => 70, 'status' => 'published'], true],
    'learning paths' => ['learning-paths', LearningPath::class, ['title' => 'Jalur QA', 'slug' => '', 'status' => 'published'], true],
    'courses' => ['courses', Course::class, ['title' => 'Kelas QA', 'slug' => '', 'status' => 'published'], true],
]);
test('permanent article deletion is limited to authorized roles', function () {
    $author = qaUser('author');
    $post = qaPost(['created_by' => $author->id]);
    $post->delete();
    $this->actingAs($author)->delete(route('admin.posts.force', $post->id))->assertForbidden();
    expect(Post::withTrashed()->find($post->id))->not->toBeNull();
    $admin = qaUser('admin');
    $this->actingAs($admin)->delete(route('admin.posts.force', $post->id))->assertRedirect();
    expect(Post::withTrashed()->find($post->id))->toBeNull();
});

test('bulk action does not partially alter posts when one is unauthorized', function () {
    $author = qaUser('author');
    $owned = qaPost(['created_by' => $author->id]);
    $other = qaPost(['title' => 'Other', 'slug' => 'other', 'created_by' => qaUser('author')->id]);
    $this->actingAs($author)->post(route('admin.posts.bulk'), ['ids' => [$owned->id, $other->id], 'action' => 'trash'])->assertForbidden();
    expect($owned->fresh())->not->toBeNull();
    expect($other->fresh())->not->toBeNull();
});

test('valid menu item appears publicly and cache refreshes after update', function () {
    $admin = qaUser();
    $menu = Menu::create(['location' => 'header', 'name' => 'Header']);
    $this->actingAs($admin)->post(route('admin.menus.items.store', $menu), ['label' => 'Panduan', 'url' => '/panduan', 'sort_order' => 1, 'is_active' => 1])->assertRedirect();
    $item = $menu->items()->firstOrFail();
    $this->get('/')->assertOk()->assertSee('Panduan');
    $this->actingAs($admin)->patch(route('admin.menus.items.update', [$menu, $item]), ['label' => 'Panduan Baru', 'url' => '/panduan', 'sort_order' => 1])->assertRedirect();
    $this->get('/')->assertOk()->assertDontSee('Panduan Baru');
});

test('course lesson order drives previous and next links', function () {
    $course = Course::create(['title' => 'Urutan QA', 'slug' => 'urutan-qa', 'status' => 'published']);
    $laterModule = $course->modules()->create(['title' => 'Modul Dua', 'sort_order' => 20]);
    $firstModule = $course->modules()->create(['title' => 'Modul Satu', 'sort_order' => 10]);
    $later = $laterModule->lessons()->create(['title' => 'Pelajaran Akhir', 'sort_order' => 1]);
    $second = $firstModule->lessons()->create(['title' => 'Pelajaran Kedua', 'sort_order' => 2]);
    $first = $firstModule->lessons()->create(['title' => 'Pelajaran Awal', 'sort_order' => 1]);
    $this->get(route('course.show', $course))->assertOk()->assertSeeInOrder(['Modul Satu', 'Pelajaran Awal', 'Pelajaran Kedua', 'Modul Dua', 'Pelajaran Akhir']);
    $this->get(route('course.lesson', [$course, $first]))->assertOk()->assertSee(route('course.lesson', [$course, $second]), false);
    $this->get(route('course.lesson', [$course, $later]))->assertOk()->assertSee(route('course.lesson', [$course, $second]), false);
});
