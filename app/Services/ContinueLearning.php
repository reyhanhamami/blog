<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\DB;

class ContinueLearning
{
    public function forUser(int $userId): ?array
    {
        $activities = DB::table('course_activity')->where('user_id', $userId)
            ->orderByDesc('last_opened_at')->get(['course_id', 'last_lesson_id']);
        if ($activities->isEmpty()) {
            return null;
        }
        $courses = Course::whereIn('id', $activities->pluck('course_id'))->where('status', 'published')
            ->with('modules.lessons')->get()->keyBy('id');
        $lessonIds = $courses->flatMap(fn ($course) => $course->modules->flatMap->lessons->pluck('id'));
        $completedIds = DB::table('course_progress')->where('user_id', $userId)
            ->whereIn('course_lesson_id', $lessonIds)->pluck('course_lesson_id')->flip();
        foreach ($activities as $activity) {
            $course = $courses->get($activity->course_id);
            if (! $course) {
                continue;
            }
            $lessons = $course->modules->flatMap->lessons->values();
            $incomplete = $lessons->first(fn ($lesson) => ! $completedIds->has($lesson->id));
            if ($incomplete) {
                return [
                    'course' => $course, 'lesson' => $incomplete,
                    'completed' => $lessons->filter(fn ($lesson) => $completedIds->has($lesson->id))->count(),
                    'total' => $lessons->count(),
                ];
            }
        }

        return null;
    }
}
