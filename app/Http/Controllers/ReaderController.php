<?php

namespace App\Http\Controllers;

use App\Enums\CourseLessonType;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Post;
use App\Services\ContinueLearning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReaderController extends Controller
{
    public function account(Request $request)
    {
        $id = $request->user()->id;
        $bookmarks = Post::published()->whereIn('id', DB::table('bookmarks')->where('user_id', $id)->select('post_id'))->latest('published_at')->get();
        $history = Post::published()->whereIn('id', DB::table('reading_history')->where('user_id', $id)->select('post_id'))->latest('published_at')->take(10)->get();
        $attempts = DB::table('quiz_attempts')->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')->where('user_id', $id)->latest('quiz_attempts.created_at')->select('quizzes.title', 'quizzes.slug', 'quiz_attempts.score', 'quiz_attempts.created_at')->take(10)->get();
        $completed = DB::table('course_progress')->where('user_id', $id)->count();
        $continueLearning = app(ContinueLearning::class)->forUser($id);
        $hasCourseActivity = DB::table('course_activity')->where('user_id', $id)->exists();

        return view('reader.account', compact('bookmarks', 'history', 'attempts', 'completed', 'continueLearning', 'hasCourseActivity'));
    }

    public function bookmark(Request $request, Post $post)
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $key = ['user_id' => $request->user()->id, 'post_id' => $post->id];
        if (DB::table('bookmarks')->where($key)->exists()) {
            DB::table('bookmarks')->where($key)->delete();
            $message = 'Bookmark dihapus.';
        } else {
            DB::table('bookmarks')->insert($key + ['created_at' => now(), 'updated_at' => now()]);
            $message = 'Artikel disimpan.';
        }

        return back()->with('success', $message);
    }

    public function lesson(Course $course, CourseLesson $lesson)
    {
        abort_unless($course->status === 'published', 404);
        $course->load('modules.lessons');
        $lesson->load(['quiz', 'post', 'video']);
        $lessons = $course->modules->flatMap->lessons->values();
        $index = $lessons->search(fn ($candidate) => $candidate->id === $lesson->id);
        abort_if($index === false, 404);
        if (auth()->check()) {
            DB::table('course_activity')->updateOrInsert(
                ['user_id' => auth()->id(), 'course_id' => $course->id],
                ['last_lesson_id' => $lesson->id, 'last_opened_at' => now()]
            );
        }
        $completed = auth()->check() && DB::table('course_progress')->where('user_id', auth()->id())->where('course_lesson_id', $lesson->id)->exists();

        return view('public.lesson', ['course' => $course, 'lesson' => $lesson, 'previous' => $lessons->get($index - 1), 'next' => $lessons->get($index + 1), 'completed' => $completed, 'total' => $lessons->count(), 'completedCount' => auth()->check() ? DB::table('course_progress')->where('user_id', auth()->id())->whereIn('course_lesson_id', $lessons->pluck('id'))->count() : 0]);
    }

    public function complete(Request $request, Course $course, CourseLesson $lesson)
    {
        abort_unless($course->status === 'published' && DB::table('course_modules')->where('id', $lesson->course_module_id)->where('course_id', $course->id)->exists(), 404);
        abort_if($lesson->type === CourseLessonType::Quiz, 403);
        DB::table('course_progress')->updateOrInsert(['user_id' => $request->user()->id, 'course_lesson_id' => $lesson->id], ['completed_at' => now()]);

        return back()->with('success', 'Pelajaran ditandai selesai.');
    }
}
