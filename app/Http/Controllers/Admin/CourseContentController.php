<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Services\Content\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

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

    public function createLesson(Course $course, CourseModule $module)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id, 404);

        return view('admin.course-content.lesson-form', ['course' => $course, 'module' => $module, 'lesson' => new CourseLesson]);
    }

    public function editLesson(Course $course, CourseModule $module, CourseLesson $lesson)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id && $lesson->course_module_id === $module->id, 404);

        return view('admin.course-content.lesson-form', compact('course', 'module', 'lesson'));
    }

    public function storeLesson(Request $request, Course $course, CourseModule $module, HtmlSanitizer $sanitizer)
    {
        Gate::authorize('manage-content');
        abort_unless($module->course_id === $course->id, 404);
        $data = $this->lessonData($request, $sanitizer);
        $module->lessons()->create($data);

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
        $data = $request->validate(['title' => ['required', 'string', 'max:191'], 'content' => ['nullable', 'string'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $data['content'] = $sanitizer->clean($data['content'] ?? '');

        return $data;
    }
}
