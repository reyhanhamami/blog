<?php

namespace App\Services;

use App\Enums\CourseLessonType;
use App\Enums\QuizQuestionType;
use App\Models\CourseLesson;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizGrader
{
    public function grade(Quiz $quiz, array $answers, ?int $userId = null, ?CourseLesson $lesson = null): array
    {
        abort_unless($quiz->status === 'published', 404);
        $quiz->loadMissing('questions.options');
        if ($quiz->questions->isEmpty()) {
            throw ValidationException::withMessages(['answers' => 'Kuis belum memiliki pertanyaan.']);
        }
        if ($lesson) {
            abort_unless($lesson->type === CourseLessonType::Quiz && $lesson->quiz_id === $quiz->id && $lesson->module()->whereHas('course', fn ($q) => $q->where('status', 'published'))->exists(), 404);
        }

        $correct = 0;
        $earnedPoints = 0;
        $totalPoints = 0;
        $details = [];
        foreach ($quiz->questions as $question) {
            if (! array_key_exists($question->id, $answers)) {
                throw ValidationException::withMessages(['answers' => 'Jawab semua pertanyaan terlebih dahulu.']);
            }
            $raw = $answers[$question->id];
            $selected = is_array($raw) ? $raw : [$raw];
            if ($selected === [] || count($selected) !== count(array_unique(array_map('strval', $selected)))) {
                throw ValidationException::withMessages(['answers' => 'Pilihan jawaban tidak valid.']);
            }
            $valid = $question->options->pluck('id')->all();
            $selected = array_map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT), $selected);
            if (in_array(false, $selected, true) || array_diff($selected, $valid)) {
                throw ValidationException::withMessages(['answers' => 'Pilihan jawaban tidak valid.']);
            }
            if ($question->type !== QuizQuestionType::MultipleChoice && count($selected) !== 1) {
                throw ValidationException::withMessages(['answers' => 'Pilih satu jawaban.']);
            }
            sort($selected);
            $expected = $question->options->where('is_correct', true)->pluck('id')->all();
            sort($expected);
            $isCorrect = $selected === $expected;
            $correct += (int) $isCorrect;
            $totalPoints += $question->points;
            $earnedPoints += $isCorrect ? $question->points : 0;
            $details[] = [
                'question' => $question->question,
                'correct' => $isCorrect,
                'selected' => $question->options->whereIn('id', $selected)->pluck('label')->all(),
                'expected' => $question->options->where('is_correct', true)->pluck('label')->all(),
                'explanation' => $question->explanation,
            ];
        }
        $total = $quiz->questions->count();
        $score = (int) round($earnedPoints / $totalPoints * 100);
        $passed = $score >= $quiz->passing_score;
        DB::transaction(function () use ($quiz, $userId, $lesson, $correct, $total, $score, $passed) {
            DB::table('quiz_attempts')->insert([
                'quiz_id' => $quiz->id, 'course_lesson_id' => $lesson?->id,
                'user_id' => $userId, 'correct_count' => $correct, 'total_count' => $total,
                'score' => $score, 'created_at' => now(),
            ]);
            if ($userId && $lesson && (! $lesson->requires_pass || $passed)) {
                DB::table('course_progress')->updateOrInsert(
                    ['user_id' => $userId, 'course_lesson_id' => $lesson->id],
                    ['completed_at' => now()]
                );
            }
        });

        return compact('correct', 'total', 'score', 'passed', 'details');
    }
}
