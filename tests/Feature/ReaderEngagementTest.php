<?php

use App\Models\Course;
use App\Models\Page;
use App\Models\Post;
use App\Models\Question;
use App\Models\User;

function readerPost(): Post
{
    return Post::create(['title' => 'Artikel Baca', 'slug' => 'artikel-baca', 'status' => 'published', 'published_at' => now()->subMinute(), 'content' => '<p>Isi</p>']);
}

test('reader can register bookmark and keep reading history', function () {
    $this->post('/register', ['name' => 'Pembaca', 'email' => 'reader@example.test', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertRedirect('/akun');
    $reader = User::where('email', 'reader@example.test')->firstOrFail();
    expect($reader->role)->toBe('reader');
    $post = readerPost();
    $this->actingAs($reader)->get('/artikel-baca')->assertOk();
    $this->actingAs($reader)->post(route('reader.bookmark', $post))->assertRedirect();
    $this->assertDatabaseHas('bookmarks', ['user_id' => $reader->id, 'post_id' => $post->id]);
    $this->assertDatabaseHas('reading_history', ['user_id' => $reader->id, 'post_id' => $post->id]);
    $this->actingAs($reader)->get('/akun')->assertOk()->assertSee('Artikel Baca');
});

test('course completion is stored for logged in reader', function () {
    $reader = User::factory()->create(['role' => 'reader']);
    $course = Course::create(['title' => 'Kelas', 'slug' => 'kelas', 'status' => 'published']);
    $module = $course->modules()->create(['title' => 'Modul', 'sort_order' => 1]);
    $lesson = $module->lessons()->create(['title' => 'Pelajaran', 'sort_order' => 1, 'content' => '<p>Belajar</p>']);
    $this->actingAs($reader)->post(route('reader.lesson.complete', [$course, $lesson]))->assertRedirect();
    $this->assertDatabaseHas('course_progress', ['user_id' => $reader->id, 'course_lesson_id' => $lesson->id]);
    $this->actingAs($reader)->get(route('course.lesson', [$course, $lesson]))->assertOk()->assertSee('1 / 1 selesai');
});

test('questions are moderated before appearing publicly', function () {
    $post = readerPost();
    $this->post(route('post.question', $post), ['name' => 'Nina', 'email' => 'nina@example.test', 'body' => 'Bagaimana cara memulai?'])->assertRedirect();
    $question = Question::firstOrFail();
    $this->get('/artikel-baca')->assertDontSee('Bagaimana cara memulai?');
    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor)->patch(route('admin.questions.update', $question), ['status' => 'approved'])->assertRedirect();
    $this->get('/artikel-baca')->assertSee('Bagaimana cara memulai?');
});

test('seeded editorial policy is public and editable', function () {
    $this->seed();
    $this->get('/editorial-policy')->assertOk()->assertSee('Kebijakan Editorial');
    $editor = User::factory()->create(['role' => 'editor']);
    $page = Page::where('slug', 'editorial-policy')->firstOrFail();
    $this->actingAs($editor)->patch(route('admin.pages.update', $page), ['title' => 'Kebijakan Konten', 'slug' => 'editorial-policy', 'content' => '<p>Ditinjau tim.</p>', 'is_published' => '1'])->assertRedirect();
    $this->get('/editorial-policy')->assertSee('Ditinjau tim.');
});
