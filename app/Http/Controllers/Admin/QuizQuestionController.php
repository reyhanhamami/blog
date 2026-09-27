<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuizQuestionType;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuizQuestionController extends Controller
{
    public function index(Quiz $quiz)
    {
        Gate::authorize('manage-content');

        return view('admin.quiz-questions.index', ['quiz' => $quiz->load('questions.options')]);
    }

    public function create(Quiz $quiz)
    {
        Gate::authorize('manage-content');

        return view('admin.quiz-questions.form', ['quiz' => $quiz, 'question' => new QuizQuestion]);
    }

    public function edit(Quiz $quiz, QuizQuestion $question)
    {
        Gate::authorize('manage-content');
        abort_unless($question->quiz_id === $quiz->id, 404);

        return view('admin.quiz-questions.form', ['quiz' => $quiz, 'question' => $question->load('options')]);
    }

    public function store(Request $request, Quiz $quiz)
    {
        Gate::authorize('manage-content');
        $this->save($request, $quiz, new QuizQuestion);

        return redirect()->route('admin.quizzes.questions.index', $quiz)->with('success', 'Pertanyaan berhasil dibuat.');
    }

    public function update(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        Gate::authorize('manage-content');
        abort_unless($question->quiz_id === $quiz->id, 404);
        $this->save($request, $quiz, $question);

        return redirect()->route('admin.quizzes.questions.index', $quiz)->with('success', 'Pertanyaan diperbarui.');
    }

    public function destroy(Quiz $quiz, QuizQuestion $question)
    {
        Gate::authorize('manage-content');
        abort_unless($question->quiz_id === $quiz->id, 404);
        $question->delete();

        return back()->with('success', 'Pertanyaan dihapus.');
    }

    private function save(Request $request, Quiz $quiz, QuizQuestion $question): void
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'type' => ['nullable', Rule::enum(QuizQuestionType::class)],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'options' => ['required_unless:type,true_false', 'array', 'min:2', 'max:4'],
            'options.*' => ['nullable', 'string', 'max:1000'],
            'correct' => ['nullable', 'integer', 'min:0'],
            'correct_options' => ['nullable', 'array'],
            'correct_options.*' => ['integer', 'min:0'],
            'correct_boolean' => ['nullable', Rule::in(['true', 'false'])],
        ]);
        $type = QuizQuestionType::from($data['type'] ?? QuizQuestionType::SingleChoice->value);
        if ($type === QuizQuestionType::TrueFalse) {
            if (! isset($data['correct_boolean'])) {
                throw ValidationException::withMessages(['correct_boolean' => 'Pilih jawaban benar.']);
            }
            $options = ['Benar', 'Salah'];
            $correctIndices = [$data['correct_boolean'] === 'true' ? 0 : 1];
        } else {
            $options = collect($data['options'])->map(fn ($label) => trim((string) $label))->filter(fn ($label) => $label !== '')->all();
            if (count($options) < 2) {
                throw ValidationException::withMessages(['options' => 'Isi minimal dua pilihan.']);
            }
            $correctIndices = $type === QuizQuestionType::SingleChoice
                ? (isset($data['correct']) ? [(int) $data['correct']] : [])
                : array_map('intval', $data['correct_options'] ?? []);
            $correctIndices = array_values(array_unique($correctIndices));
            if (($type === QuizQuestionType::SingleChoice && count($correctIndices) !== 1)
                || ($type === QuizQuestionType::MultipleChoice && count($correctIndices) < 1)
                || array_diff($correctIndices, array_keys($options))) {
                throw ValidationException::withMessages(['correct' => 'Pilih jawaban benar yang valid.']);
            }
        }
        DB::transaction(function () use ($quiz, $question, $data, $type, $options, $correctIndices) {
            $question->fill([
                'quiz_id' => $quiz->id, 'question' => $data['question'], 'type' => $type,
                'explanation' => $data['explanation'] ?? null, 'sort_order' => $data['sort_order'],
                'points' => $data['points'] ?? 1,
            ])->save();
            $question->options()->delete();
            foreach ($options as $index => $label) {
                $question->options()->create(['label' => $label, 'is_correct' => in_array($index, $correctIndices, true)]);
            }
        });
    }
}
