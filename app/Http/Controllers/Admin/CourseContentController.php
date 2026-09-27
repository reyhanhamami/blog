<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLessonType;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Post;
use App\Models\Quiz;
use App\Models\Video;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseContentController extends Controller
{
    public function index(Course $course)
    {
        Gate::authorize('manage-content');

        return view('admin.course-content.index', ['course' => $course->load('modules.lessons')]);
    }

    public function storeModule(Request $request, Course $course)
    {
        Gate::authorize('manage-content');
        $course->modules()->create($request->validate(['title' => ['required', 'string', 'max:191'], 'sort_order' => ['required', 'integer', 'min:0']]));

        return back()->with('success', 'Modul dibuat.');
    }

    public function deleteModule(Course $course, CourseModule $module)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id, 404);
        $module->delete();

        return back()->with('success', 'Modul dihapus.');
    }

    private function formData(Course $course, CourseModule $module, CourseLesson $lesson): array
    {
        return compact('course', 'module', 'lesson') + [
            'posts' => Post::published()->when($lesson->post_id, fn ($q) => $q->orWhere('id', $lesson->post_id))->orderBy('title')->get(['id', 'title']),
            'videos' => Video::published()->when($lesson->video_id, fn ($q) => $q->orWhere('id', $lesson->video_id))->orderBy('title')->get(['id', 'title']),
            'quizzes' => Quiz::where('status', 'published')->when($lesson->quiz_id, fn ($q) => $q->orWhere('id', $lesson->quiz_id))->orderBy('title')->get(['id', 'title']),
        ];
    }

    public function createLesson(Course $course, CourseModule $module)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id, 404);

        return view('admin.course-content.lesson-form', $this->formData($course, $module, new CourseLesson));
    }

    public function editLesson(Course $course, CourseModule $module, CourseLesson $lesson)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id && $lesson->course_module_id === $module->id, 404);

        return view('admin.course-content.lesson-form', $this->formData($course, $module, $lesson));
    }

    public function storeLesson(Request $request, Course $course, CourseModule $module, HtmlSanitizer $sanitizer)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id, 404);
        $module->lessons()->create($this->lessonData($request, $sanitizer));

        return redirect()->route('admin.courses.content.index', $course)->with('success', 'Pelajaran dibuat.');
    }

    public function updateLesson(Request $request, Course $course, CourseModule $module, CourseLesson $lesson, HtmlSanitizer $sanitizer)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id && $lesson->course_module_id === $module->id, 404);
        $lesson->update($this->lessonData($request, $sanitizer));

        return redirect()->route('admin.courses.content.index', $course)->with('success', 'Pelajaran diperbarui.');
    }

    public function deleteLesson(Course $course, CourseModule $module, CourseLesson $lesson)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id && $lesson->course_module_id === $module->id, 404);
        $lesson->delete();

        return back()->with('success', 'Pelajaran dihapus.');
    }

    private function lessonData(Request $request, HtmlSanitizer $sanitizer): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'type' => ['nullable', Rule::enum(CourseLessonType::class)],
            'content' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'post_id' => ['nullable', 'exists:posts,id'],
            'video_id' => ['nullable', 'exists:videos,id'],
            'quiz_id' => ['nullable', 'exists:quizzes,id'],
            'requires_pass' => ['nullable', 'boolean'],
        ]);
        $type = CourseLessonType::from($data['type'] ?? CourseLessonType::Custom->value);
        $relation = match ($type) {
            CourseLessonType::Article => 'post_id',
            CourseLessonType::Video => 'video_id',
            CourseLessonType::Quiz => 'quiz_id',
            CourseLessonType::Custom => null,
        };
        if ($relation && empty($data[$relation])) {
            throw ValidationException::withMessages([$relation => 'Pilih materi yang sesuai.']);
        }
        $data['type'] = $type;
        $data['content'] = $type === CourseLessonType::Custom ? $sanitizer->clean($data['content'] ?? '') : null;
        foreach (['post_id', 'video_id', 'quiz_id'] as $key) {
            $data[$key] = $key === $relation ? ($data[$key] ?? null) : null;
        }
        $data['requires_pass'] = $type === CourseLessonType::Quiz && $request->boolean('requires_pass');

        return $data;
    }
}
