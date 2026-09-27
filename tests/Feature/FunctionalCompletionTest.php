<?php

use App\Enums\CourseLessonType;
use App\Enums\QuizQuestionType;
use App\Livewire\QuizPlayer;
use App\Models\Category;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Topic;
use App\Models\User;
use App\Models\Video;
use App\Services\ContinueLearning;
use App\Services\QuizGrader;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function completionQuiz(string $slug = 'kuis-fungsi', int $passing = 70): Quiz
{
    return Quiz::create(['title' => 'Kuis '.$slug, 'slug' => $slug, 'status' => 'published', 'passing_score' => $passing]);
}

function completionQuestion(Quiz $quiz, QuizQuestionType $type, array $correct): array
{
    $question = $quiz->questions()->create(['question' => 'Pilih jawaban benar', 'type' => $type, 'sort_order' => $quiz->questions()->count()]);
    $options = [];
    foreach ($type === QuizQuestionType::TrueFalse ? ['Benar', 'Salah'] : ['A', 'B', 'C'] as $index => $label) {
        $options[] = $question->options()->create(['label' => $label, 'is_correct' => in_array($index, $correct, true)]);
    }

    return [$question, $options];
}

function completionPost(string $slug, int $minutes = 1, array $extra = []): Post
{
    return Post::create(array_merge(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'published', 'published_at' => now()->subMinutes($minutes), 'content' => '<p>Isi artikel</p>'], $extra));
}

test('admin validates and stores all three question types with reusable edit form', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $quiz = completionQuiz();
    $route = route('admin.quizzes.questions.store', $quiz);

    $this->actingAs($editor)->post($route, ['question' => 'Satu', 'type' => 'single_choice', 'sort_order' => 0, 'options' => ['A', 'B', '', ''], 'correct' => 1])->assertRedirect();
    $single = $quiz->questions()->firstOrFail();
    expect($single->type)->toBe(QuizQuestionType::SingleChoice);
    expect($single->options()->count())->toBe(2);
    $this->actingAs($editor)->post($route, ['question' => 'Banyak', 'type' => 'multiple_choice', 'sort_order' => 1, 'options' => ['A', 'B', 'C'], 'correct_options' => [0, 1]])->assertRedirect();
    $multi = $quiz->questions()->where('question', 'Banyak')->firstOrFail();
    expect($multi->options()->where('is_correct', true)->count())->toBe(2);
    $this->actingAs($editor)->post($route, ['question' => 'Benar?', 'type' => 'true_false', 'sort_order' => 2, 'correct_boolean' => 'false'])->assertRedirect();
    $boolean = $quiz->questions()->where('question', 'Benar?')->firstOrFail();
    expect($boolean->options()->pluck('label')->all())->toBe(['Benar', 'Salah']);
    expect($boolean->options()->where('is_correct', true)->value('label'))->toBe('Salah');
    $this->actingAs($editor)->get(route('admin.quizzes.questions.edit', [$quiz, $boolean]))->assertOk()->assertSee('correct_boolean');
    $this->actingAs($editor)->post($route, ['question' => 'Rusak', 'type' => 'single_choice', 'sort_order' => 3, 'options' => ['A', 'B']])->assertSessionHasErrors('correct');
    $this->actingAs($editor)->post($route, ['question' => 'Rusak', 'type' => 'multiple_choice', 'sort_order' => 3, 'options' => ['A', 'B']])->assertSessionHasErrors('correct');
});

test('multiple choice requires exact set and ignores client scoring fields', function () {
    $quiz = completionQuiz();
    [$question, $options] = completionQuestion($quiz, QuizQuestionType::MultipleChoice, [0, 1]);
    $endpoint = route('quiz.submit', $quiz);

    $cases = [
        [[$options[0]->id, $options[1]->id], 100],
        [[$options[0]->id], 0],
        [[$options[0]->id, $options[1]->id, $options[2]->id], 0],
    ];
    foreach ($cases as [$selection, $score]) {
        $this->post($endpoint, ['answers' => [$question->id => $selection], 'score' => 100, 'is_correct' => 1, 'passing_score' => 0])
            ->assertSessionHas('quiz_result', fn ($result) => $result['score'] === $score && $result['passed'] === ($score >= 70));
    }
    expect(DB::table('quiz_attempts')->where('quiz_id', $quiz->id)->orderBy('id')->pluck('score')->all())->toBe([100, 0, 0]);
    $this->post($endpoint, ['answers' => [$question->id => [$options[0]->id, 999999]]])->assertSessionHasErrors('answers');
});

test('single choice and true false score on server and player renders correct controls', function () {
    $quiz = completionQuiz();
    [$single, $singleOptions] = completionQuestion($quiz, QuizQuestionType::SingleChoice, [0]);
    [$boolean, $booleanOptions] = completionQuestion($quiz, QuizQuestionType::TrueFalse, [1]);
    $grader = app(QuizGrader::class);
    expect($grader->grade($quiz, [$single->id => $singleOptions[0]->id, $boolean->id => $booleanOptions[1]->id])['score'])->toBe(100);
    expect($grader->grade($quiz, [$single->id => $singleOptions[1]->id, $boolean->id => $booleanOptions[0]->id])['score'])->toBe(0);
    Livewire::test(QuizPlayer::class, ['quiz' => $quiz])
        ->assertSee('Pertanyaan 1 dari 2')->assertSeeHtml('type="radio"')
        ->set('answers.'.$single->id, (string) $singleOptions[0]->id)
        ->call('next')->assertSee('Pertanyaan 2 dari 2')
        ->set('answers.'.$boolean->id, (string) $booleanOptions[1]->id)
        ->call('submit')->assertSee('Skor: 2 / 2')
        ->call('retry')->assertSee('Pertanyaan 1 dari 2');
    $multiQuiz = completionQuiz('kuis-multi');
    completionQuestion($multiQuiz, QuizQuestionType::MultipleChoice, [0, 1]);
    Livewire::test(QuizPlayer::class, ['quiz' => $multiQuiz])->assertSeeHtml('type="checkbox"');
});

test('article quiz CTA is conditional and navigation excludes unavailable posts', function () {
    $quiz = completionQuiz();
    $older = completionPost('lama', 30);
    $current = completionPost('tengah', 20, ['quiz_id' => $quiz->id]);
    $newer = completionPost('baru', 10);
    completionPost('draf', 5, ['status' => 'draft']);
    completionPost('terjadwal', -10);
    $deleted = completionPost('terhapus', 2);
    $deleted->delete();
    $this->get(route('post.show', $current->slug))->assertOk()->assertSee('Mulai Quiz')->assertSee(route('quiz.show', $quiz))
        ->assertSee('rel="prev" href="'.route('post.show', $older->slug).'"', false)
        ->assertSee('rel="next" href="'.route('post.show', $newer->slug).'"', false)
        ->assertDontSee('Draf')->assertDontSee('Terjadwal');
    $this->get(route('post.show', $older->slug))->assertDontSee('Mulai Quiz');
    $quiz->update(['status' => 'draft']);
    $this->get(route('post.show', $current->slug))->assertDontSee('Mulai Quiz');
    $this->get(route('quiz.show', $quiz))->assertNotFound();
});

test('article navigation follows learning path and category priority', function () {
    $category = Category::create(['name' => 'PHP', 'slug' => 'php']);
    $first = completionPost('awal', 40);
    $current = completionPost('pusat', 30, ['category_id' => $category->id]);
    $sameCategory = completionPost('kategori', 20, ['category_id' => $category->id]);
    $global = completionPost('global', 10);
    $this->get(route('post.show', $current->slug))->assertSee('rel="next" href="'.route('post.show', $sameCategory->slug).'"', false);
    $path = LearningPath::create(['title' => 'Jalur', 'slug' => 'jalur', 'status' => 'published']);
    $path->items()->create(['post_id' => $current->id, 'sort_order' => 1]);
    $path->items()->create(['post_id' => $global->id, 'sort_order' => 2]);
    $this->get(route('post.show', $current->slug))->assertSee('rel="next" href="'.route('post.show', $global->slug).'"', false);
});

test('course quiz lesson completes only on submission and keeps readers isolated', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $readerA = User::factory()->create(['role' => 'reader']);
    $readerB = User::factory()->create(['role' => 'reader']);
    $quiz = completionQuiz();
    [$question, $options] = completionQuestion($quiz, QuizQuestionType::SingleChoice, [0]);
    $course = Course::create(['title' => 'Kelas', 'slug' => 'kelas-quiz', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Modul', 'sort_order' => 1]);
    $this->actingAs($editor)->get(route('admin.courses.lessons.create', [$course, $module]))->assertOk()->assertSee('Quiz');
    $this->actingAs($editor)->post(route('admin.courses.lessons.store', [$course, $module]), ['title' => 'Quiz lesson', 'type' => 'quiz', 'quiz_id' => $quiz->id, 'requires_pass' => 1, 'sort_order' => 1])->assertRedirect();
    $lesson = $module->lessons()->firstOrFail();
    expect($lesson->type)->toBe(CourseLessonType::Quiz);
    $this->actingAs($readerA)->get(route('course.lesson', [$course, $lesson]))->assertOk()->assertSee('Kuis '.$quiz->slug);
    $this->post(route('reader.lesson.complete', [$course, $lesson]))->assertForbidden();
    expect(DB::table('course_progress')->count())->toBe(0);
    $grader = app(QuizGrader::class);
    $grader->grade($quiz, [$question->id => $options[1]->id], $readerA->id, $lesson);
    expect(DB::table('course_progress')->count())->toBe(0);
    $grader->grade($quiz, [$question->id => $options[0]->id], $readerA->id, $lesson);
    $this->assertDatabaseHas('course_progress', ['user_id' => $readerA->id, 'course_lesson_id' => $lesson->id]);
    $this->assertDatabaseMissing('course_progress', ['user_id' => $readerB->id, 'course_lesson_id' => $lesson->id]);
    $this->actingAs($readerB);
    Livewire::test(QuizPlayer::class, ['quiz' => $quiz, 'lesson' => $lesson])
        ->set('answers.'.$question->id, (string) $options[0]->id)
        ->call('submit')->assertSee('Skor: 1 / 1')->assertSee('1 / 1 pelajaran selesai');
    $this->assertDatabaseHas('course_progress', ['user_id' => $readerB->id, 'course_lesson_id' => $lesson->id]);
});

test('continue learning picks first incomplete lesson in latest active unfinished course', function () {
    $readerA = User::factory()->create(['role' => 'reader']);
    $readerB = User::factory()->create(['role' => 'reader']);
    $course = Course::create(['title' => 'Belajar', 'slug' => 'belajar', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Dasar', 'sort_order' => 1]);
    $first = $module->lessons()->create(['title' => 'Pertama', 'sort_order' => 1]);
    $second = $module->lessons()->create(['title' => 'Kedua', 'sort_order' => 2]);
    $this->actingAs($readerA)->get(route('course.lesson', [$course, $second]))->assertOk();
    expect(app(ContinueLearning::class)->forUser($readerA->id)['lesson']->id)->toBe($first->id);
    DB::table('course_progress')->insert(['user_id' => $readerA->id, 'course_lesson_id' => $first->id, 'completed_at' => now()]);
    expect(app(ContinueLearning::class)->forUser($readerA->id)['lesson']->id)->toBe($second->id);
    expect(app(ContinueLearning::class)->forUser($readerB->id))->toBeNull();
    $this->actingAs($readerA)->get(route('reader.account'))->assertSee('Lanjutkan Belajar')->assertSee('Kedua');
    DB::table('course_progress')->insert(['user_id' => $readerA->id, 'course_lesson_id' => $second->id, 'completed_at' => now()]);
    expect(app(ContinueLearning::class)->forUser($readerA->id))->toBeNull();
    $this->actingAs($readerA)->get(route('reader.account'))->assertDontSee('Lanjutkan Belajar');
});

test('learning path supports a quiz item without exposing draft quiz', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $quiz = completionQuiz();
    $path = LearningPath::create(['title' => 'Jalur', 'slug' => 'jalur', 'status' => 'published']);
    $this->actingAs($editor)->get(route('admin.learning-paths.items.index', $path))->assertOk()->assertSee('Quiz');
    $this->actingAs($editor)->post(route('admin.learning-paths.items.store', $path), ['type' => 'quiz', 'quiz_id' => $quiz->id, 'sort_order' => 1])->assertRedirect();
    $this->get(route('path.show', $path))->assertSee($quiz->title)->assertSee(route('quiz.show', $quiz));
    $quiz->update(['status' => 'draft']);
    $this->get(route('path.show', $path))->assertDontSee($quiz->title);
});
test('article editor persists primary quiz and reloads searchable selection', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $quiz = completionQuiz();
    $post = completionPost('artikel-quiz');
    $this->actingAs($editor)->patch(route('admin.posts.update', $post), [
        'title' => $post->title, 'slug' => $post->slug, 'content' => $post->content,
        'content_type' => 'article', 'status' => 'published', 'quiz_id' => $quiz->id,
    ])->assertRedirect();
    expect($post->fresh()->quiz_id)->toBe($quiz->id);
    $this->actingAs($editor)->get(route('admin.posts.edit', $post))
        ->assertOk()->assertSee('name="quiz_id"', false)
        ->assertSee('value="'.$quiz->id.'" selected', false);
    $this->get(route('post.show', $post->slug))->assertSee(route('quiz.show', $quiz));
});

test('same topic wins over category in article chronological navigation', function () {
    $category = Category::create(['name' => 'Backend', 'slug' => 'backend']);
    $topic = Topic::create(['name' => 'PHP', 'slug' => 'php']);
    $current = completionPost('topik-awal', 30, ['category_id' => $category->id]);
    $current->topics()->attach($topic);
    $categoryNext = completionPost('kategori-lanjut', 20, ['category_id' => $category->id]);
    $topicNext = completionPost('topik-lanjut', 10);
    $topicNext->topics()->attach($topic);
    $this->get(route('post.show', $current->slug))
        ->assertSee('rel="next" href="'.route('post.show', $topicNext->slug).'"', false)
        ->assertDontSee('rel="next" href="'.route('post.show', $categoryNext->slug).'"', false);
});

test('course article and video lesson types retain source relation', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $post = completionPost('materi-artikel');
    $video = Video::create([
        'title' => 'Materi Video', 'slug' => 'materi-video', 'youtube_id' => 'abcdefghijk',
        'status' => 'published', 'published_at' => now()->subMinute(),
    ]);
    $course = Course::create(['title' => 'Kelas Materi', 'slug' => 'kelas-materi', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Materi', 'sort_order' => 1]);
    $url = route('admin.courses.lessons.store', [$course, $module]);
    $this->actingAs($editor)->post($url, ['title' => 'Baca', 'type' => 'article', 'post_id' => $post->id, 'sort_order' => 1])->assertRedirect();
    $this->actingAs($editor)->post($url, ['title' => 'Tonton', 'type' => 'video', 'video_id' => $video->id, 'sort_order' => 2])->assertRedirect();
    $articleLesson = $module->lessons()->where('type', 'article')->firstOrFail();
    $videoLesson = $module->lessons()->where('type', 'video')->firstOrFail();
    expect($articleLesson->post_id)->toBe($post->id);
    expect($videoLesson->video_id)->toBe($video->id);
    $this->get(route('course.lesson', [$course, $articleLesson]))->assertSee(route('post.show', $post->slug));
    $this->get(route('course.lesson', [$course, $videoLesson]))->assertSee(route('video.show', $video));
});

test('continue learning uses latest active unfinished course then falls back to older course', function () {
    $reader = User::factory()->create(['role' => 'reader']);
    $oldCourse = Course::create(['title' => 'Lama', 'slug' => 'kelas-lama', 'status' => 'published']);
    $oldLesson = $oldCourse->modules()->create(['title' => 'Modul', 'sort_order' => 1])->lessons()->create(['title' => 'Satu', 'sort_order' => 1]);
    $newCourse = Course::create(['title' => 'Baru', 'slug' => 'kelas-baru', 'status' => 'published']);
    $newLesson = $newCourse->modules()->create(['title' => 'Modul', 'sort_order' => 1])->lessons()->create(['title' => 'Dua', 'sort_order' => 1]);
    DB::table('course_activity')->insert([
        ['user_id' => $reader->id, 'course_id' => $oldCourse->id, 'last_lesson_id' => $oldLesson->id, 'last_opened_at' => now()->subDay()],
        ['user_id' => $reader->id, 'course_id' => $newCourse->id, 'last_lesson_id' => $newLesson->id, 'last_opened_at' => now()],
    ]);
    expect(app(ContinueLearning::class)->forUser($reader->id)['course']->id)->toBe($newCourse->id);
    DB::table('course_progress')->insert(['user_id' => $reader->id, 'course_lesson_id' => $newLesson->id, 'completed_at' => now()]);
    expect(app(ContinueLearning::class)->forUser($reader->id)['course']->id)->toBe($oldCourse->id);
});
test('question points are read from database and never trusted from request', function () {
    $quiz = completionQuiz('bobot');
    [$first, $firstOptions] = completionQuestion($quiz, QuizQuestionType::SingleChoice, [0]);
    [$second, $secondOptions] = completionQuestion($quiz, QuizQuestionType::MultipleChoice, [0, 1]);
    $first->update(['points' => 3]);
    $second->update(['points' => 1]);
    $this->post(route('quiz.submit', $quiz), [
        'answers' => [
            $first->id => $firstOptions[0]->id,
            $second->id => [$secondOptions[0]->id],
        ],
        'points' => 999, 'score' => 100,
    ])->assertSessionHas('quiz_result', fn ($result) => $result['correct'] === 1 && $result['total'] === 2 && $result['score'] === 75);
    $this->assertDatabaseHas('quiz_attempts', ['quiz_id' => $quiz->id, 'score' => 75]);
});
test('true and false question answers are scored independently', function () {
    $quiz = completionQuiz('benar-salah');
    [$trueQuestion, $trueOptions] = completionQuestion($quiz, QuizQuestionType::TrueFalse, [0]);
    [$falseQuestion, $falseOptions] = completionQuestion($quiz, QuizQuestionType::TrueFalse, [1]);
    $result = app(QuizGrader::class)->grade($quiz, [
        $trueQuestion->id => $trueOptions[0]->id,
        $falseQuestion->id => $falseOptions[1]->id,
    ]);
    expect($result['correct'])->toBe(2);
    expect($result['score'])->toBe(100);
    $wrong = app(QuizGrader::class)->grade($quiz, [
        $trueQuestion->id => $trueOptions[1]->id,
        $falseQuestion->id => $falseOptions[0]->id,
    ]);
    expect($wrong['score'])->toBe(0);
});

test('quiz lesson without required pass completes after a failed submission', function () {
    $reader = User::factory()->create(['role' => 'reader']);
    $quiz = completionQuiz('tanpa-lulus', 100);
    [$question, $options] = completionQuestion($quiz, QuizQuestionType::SingleChoice, [0]);
    $course = Course::create(['title' => 'Kelas', 'slug' => 'kelas-tanpa-lulus', 'status' => 'published']);
    $lesson = $course->modules()->create(['title' => 'Modul', 'sort_order' => 1])->lessons()->create([
        'title' => 'Kuis', 'type' => CourseLessonType::Quiz, 'quiz_id' => $quiz->id, 'requires_pass' => false, 'sort_order' => 1,
    ]);
    $result = app(QuizGrader::class)->grade($quiz, [$question->id => $options[1]->id], $reader->id, $lesson);
    expect($result['passed'])->toBeFalse();
    $this->assertDatabaseHas('course_progress', ['user_id' => $reader->id, 'course_lesson_id' => $lesson->id]);
});
