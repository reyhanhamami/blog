<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            'sort_order' => ['required', 'integer', 'min:0'],
            'options' => ['required', 'array', 'size:4'],
            'options.*' => ['required', 'string', 'max:1000'],
            'correct' => ['required', 'integer', 'between:0,3'],
        ]);
        DB::transaction(function () use ($quiz, $question, $data) {
            $question->fill(['quiz_id' => $quiz->id, 'question' => $data['question'], 'sort_order' => $data['sort_order']])->save();
            $question->options()->delete();
            foreach (array_values($data['options']) as $index => $label) {
                $question->options()->create(['label' => $label, 'is_correct' => $index === (int) $data['correct']]);
            }
        });
    }
}
