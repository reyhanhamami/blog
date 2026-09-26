<?php

use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Http\UploadedFile;

function editorForLearning(): User
{
    return User::factory()->create(['role' => 'editor']);
}

test('quiz questions can be authored and attempts are scored', function () {
    $editor = editorForLearning();
    $quiz = Quiz::create(['title' => 'Dasar PHP', 'slug' => 'dasar-php', 'status' => 'published', 'passing_score' => 70]);
    $this->actingAs($editor)->post(route('admin.quizzes.questions.store', $quiz), [
        'question' => 'Apa kepanjangan PHP?', 'sort_order' => 1,
        'options' => ['Personal Home Page', 'Hypertext Preprocessor', 'Private Host Protocol', 'Public Hypertext Program'], 'correct' => 1,
    ])->assertRedirect();
    $question = $quiz->questions()->firstOrFail();
    $correct = $question->options()->where('is_correct', true)->firstOrFail();
    $this->post(route('quiz.submit', $quiz), ['answers' => [$question->id => $correct->id]])->assertSessionHas('quiz_result');
    $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 100]);
});

test('learning path and course can contain ordered material', function () {
    $editor = editorForLearning();
    $post = Post::create(['title' => 'Intro', 'slug' => 'intro', 'status' => 'published', 'published_at' => now()->subMinute()]);
    $path = LearningPath::create(['title' => 'Mulai', 'slug' => 'mulai', 'status' => 'published']);
    $this->actingAs($editor)->post(route('admin.learning-paths.items.store', $path), ['post_id' => $post->id, 'sort_order' => 2])->assertRedirect();
    $this->get(route('path.show', $path))->assertOk()->assertSee('Intro');
    $course = Course::create(['title' => 'Kelas PHP', 'slug' => 'kelas-php', 'status' => 'published']);
    $this->actingAs($editor)->post(route('admin.courses.modules.store', $course), ['title' => 'Dasar', 'sort_order' => 1])->assertRedirect();
    $module = $course->modules()->firstOrFail();
    $this->actingAs($editor)->post(route('admin.courses.lessons.store', [$course, $module]), ['title' => 'Pengenalan', 'sort_order' => 1, 'content' => '<p>Materi</p>'])->assertRedirect();
    $this->get(route('course.show', $course))->assertOk()->assertSee('Pengenalan');
});

test('unsafe upload is rejected', function () {
    $editor = editorForLearning();
    $this->actingAs($editor)->post(route('admin.media.store'), ['file' => UploadedFile::fake()->create('shell.php', 2, 'application/x-php'), 'alt_text' => 'Shell'])->assertSessionHasErrors('file');
});

test('site settings update public branding', function () {
    $admin = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($admin)->patch(route('admin.settings.update'), ['site_name' => 'Besofton Knowledge'])->assertRedirect();
    $this->get('/')->assertOk()->assertSee('Besofton Knowledge');
});
