<?php

use App\Enums\CourseLessonType;
use App\Enums\LearningPathItemType;
use App\Enums\QuizQuestionType;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Media;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use App\Models\Video;
use App\Services\ContinueLearning;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

test('default database seeder contains core data and no demo content', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@gmail.com')->where('role', 'superadmin')->exists())->toBeTrue()
        ->and(User::where('email', 'reader@besofton.id')->exists())->toBeFalse()
        ->and(Post::count())->toBe(0)
        ->and(Quiz::count())->toBe(0)
        ->and(Course::count())->toBe(0)
        ->and(DB::table('settings')->where('key', 'site_name')->value('value'))->toBe('Besofton Insights');
});

test('demo seeder creates connected content and stays idempotent without overwriting edits', function () {
    $this->seed(DemoSeeder::class);

    $prompt = Post::where('slug', 'cara-membuat-prompt-ai-untuk-coding')->firstOrFail();
    $laravel = Post::where('slug', 'panduan-dasar-laravel-aplikasi-web-modern')->firstOrFail();
    $quiz = Quiz::where('slug', 'quiz-dasar-prompt-ai-coding')->firstOrFail();
    $course = Course::where('slug', 'fundamental-ai-assisted-development')->firstOrFail();
    $reader = User::where('email', 'reader@besofton.id')->firstOrFail();

    expect($prompt->status)->toBe('published')
        ->and($prompt->category?->slug)->toBe('artificial-intelligence')
        ->and($prompt->author?->slug)->toBe('besofton-developer')
        ->and($prompt->tags->pluck('slug')->all())->toContain('prompt-ai')
        ->and($prompt->topics->pluck('slug')->all())->toContain('ai-for-developers')
        ->and($prompt->quiz_id)->toBe($quiz->id)
        ->and($prompt->relatedPosts->pluck('id')->all())->toContain($laravel->id)
        ->and($prompt->sources()->count())->toBe(1)
        ->and($quiz->questions()->count())->toBe(3)
        ->and($quiz->questions->pluck('type')->all())->toContain(QuizQuestionType::SingleChoice, QuizQuestionType::MultipleChoice, QuizQuestionType::TrueFalse)
        ->and($quiz->questions->firstWhere('type', QuizQuestionType::MultipleChoice)->options()->where('is_correct', true)->count())->toBe(3)
        ->and($quiz->questions->last()->options()->count())->toBe(2)
        ->and($quiz->questions->last()->options()->where('is_correct', true)->value('label'))->toBe('Benar')
        ->and($course->modules()->count())->toBe(1)
        ->and($course->modules->first()->lessons->pluck('type')->all())->toBe([CourseLessonType::Article, CourseLessonType::Quiz]);

    $path = LearningPath::where('slug', 'ai-for-developer-dasar')->firstOrFail();
    expect($path->items->pluck('type')->all())->toBe([LearningPathItemType::Article, LearningPathItemType::Video, LearningPathItemType::Quiz])
        ->and(DB::table('bookmarks')->where('user_id', $reader->id)->where('post_id', $prompt->id)->count())->toBe(1)
        ->and(DB::table('course_progress')->where('user_id', $reader->id)->count())->toBe(1)
        ->and(app(ContinueLearning::class)->forUser($reader->id)['lesson']->type)->toBe(CourseLessonType::Quiz)
        ->and($prompt->questions()->where('status', 'approved')->withCount('answers')->first()?->answers_count)->toBe(1)
        ->and(DB::table('article_feedback')->where('post_id', $prompt->id)->count())->toBe(2)
        ->and(DB::table('newsletter_subscribers')->where('email', 'demo.reader@besofton.id')->count())->toBe(1)
        ->and(DB::table('redirects')->where('from_path', '/panduan-prompt-ai')->count())->toBe(1);
    expect(Video::where('slug', 'chatgpt-prompt-engineering-for-developers')->where('status', 'published')->exists())->toBeTrue()
        ->and(DB::table('pages')->count())->toBeGreaterThanOrEqual(2)
        ->and(DB::table('menus')->count())->toBe(2);
    $this->get(route('post.show', $prompt->slug))->assertOk()
        ->assertSee('<title>Cara Membuat Prompt AI untuk Coding | Besofton Insights</title>', false)
        ->assertDontSee('Besofton Insights | Besofton Insights')
        ->assertSee('Quiz Dasar Prompt AI untuk Coding');

    $counts = [
        User::count(), Post::withTrashed()->count(), Category::withTrashed()->count(), Quiz::withTrashed()->count(),
        Course::withTrashed()->count(), DB::table('quiz_questions')->count(), DB::table('course_lessons')->count(),
    ];
    $prompt->update(['title' => 'Judul yang diedit manual']);
    $reader->update(['password' => Hash::make('password-baru')]);
    $this->seed(DemoSeeder::class);

    expect([
        User::count(), Post::withTrashed()->count(), Category::withTrashed()->count(), Quiz::withTrashed()->count(),
        Course::withTrashed()->count(), DB::table('quiz_questions')->count(), DB::table('course_lessons')->count(),
    ])->toBe($counts)
        ->and($prompt->fresh()->title)->toBe('Judul yang diedit manual')
        ->and(Hash::check('password-baru', $reader->fresh()->password))->toBeTrue();
});

test('deleting a media record does not remove a shared site asset', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@gmail.com')->firstOrFail();
    $media = Media::create(['path' => 'favicon.svg', 'mime_type' => 'image/svg+xml', 'size' => filesize(public_path('favicon.svg')), 'alt_text' => 'Ikon situs']);

    $this->actingAs($admin)->delete(route('admin.media.destroy', $media))->assertRedirect();

    expect(is_file(public_path('favicon.svg')))->toBeTrue()
        ->and(Media::whereKey($media->id)->exists())->toBeFalse();
});
