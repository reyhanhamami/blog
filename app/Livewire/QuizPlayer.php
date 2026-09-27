<?php

namespace App\Livewire;

use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Quiz;
use App\Services\QuizGrader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuizPlayer extends Component
{
    #[Locked]
    public int $quizId;

    #[Locked]
    public ?int $courseLessonId = null;

    public array $answers = [];

    #[Locked]
    public ?array $result = null;

    #[Locked]
    public int $current = 0;

    #[Locked]
    public ?array $courseProgress = null;

    public function mount(Quiz $quiz, ?CourseLesson $lesson = null): void
    {
        abort_unless($quiz->status === 'published', 404);
        $this->quizId = $quiz->id;
        $this->courseLessonId = $lesson?->id;
    }

    public function next(): void
    {
        $quiz = Quiz::with('questions.options')->findOrFail($this->quizId);
        $question = $quiz->questions->values()->get($this->current);
        if ($question && ! isset($this->answers[$question->id])) {
            $this->addError('answers', 'Pilih jawaban sebelum melanjutkan.');

            return;
        }
        $this->current = min($this->current + 1, $quiz->questions->count() - 1);
    }

    public function retry(): void
    {
        $this->answers = [];
        $this->result = null;
        $this->current = 0;
        $this->courseProgress = null;
    }

    public function previous(): void
    {
        $this->current = max(0, $this->current - 1);
    }

    public function submit(QuizGrader $grader): void
    {
        $quiz = Quiz::with('questions.options')->findOrFail($this->quizId);
        try {
            $lesson = $this->courseLessonId ? CourseLesson::findOrFail($this->courseLessonId) : null;
            $this->result = $grader->grade($quiz, $this->answers, auth()->id(), $lesson);
            if ($lesson && auth()->check()) {
                $courseId = $lesson->module()->value('course_id');
                $total = CourseLesson::whereIn('course_module_id', CourseModule::where('course_id', $courseId)->select('id'))->count();
                $completed = DB::table('course_progress')->join('course_lessons', 'course_lessons.id', '=', 'course_progress.course_lesson_id')
                    ->join('course_modules', 'course_modules.id', '=', 'course_lessons.course_module_id')
                    ->where('course_modules.course_id', $courseId)->where('course_progress.user_id', auth()->id())->count();
                $this->courseProgress = compact('total', 'completed');
            }
        } catch (ValidationException $exception) {
            $this->addError('answers', $exception->errors()['answers'][0] ?? 'Jawaban tidak valid.');
        }
    }

    public function render()
    {
        return view('livewire.quiz-player', ['quiz' => Quiz::with('questions.options')->findOrFail($this->quizId)]);
    }
}
