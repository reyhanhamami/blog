<?php

namespace App\Livewire;

use App\Models\Quiz;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class QuizPlayer extends Component
{
    public int $quizId;

    public array $answers = [];

    public ?array $result = null;

    public function mount(Quiz $quiz): void
    {
        abort_unless($quiz->status === 'published', 404);
        $this->quizId = $quiz->id;
    }

    public function submit(): void
    {
        $quiz = Quiz::with('questions.options')->findOrFail($this->quizId);
        abort_unless($quiz->status === 'published', 404);
        $total = $quiz->questions->count();
        if ($total === 0) {
            return;
        }
        foreach ($quiz->questions as $question) {
            if (! isset($this->answers[$question->id])) {
                $this->addError('answers', 'Jawab semua pertanyaan terlebih dahulu.');

                return;
            }
        }
        $correct = 0;
        foreach ($quiz->questions as $question) {
            $option = $question->options->firstWhere('id', (int) $this->answers[$question->id]);
            if ($option?->is_correct) {
                $correct++;
            }
        }
        $score = (int) round($correct / $total * 100);
        DB::table('quiz_attempts')->insert(['quiz_id' => $quiz->id, 'user_id' => auth()->id(), 'correct_count' => $correct, 'total_count' => $total, 'score' => $score, 'created_at' => now()]);
        $this->result = ['correct' => $correct, 'total' => $total, 'score' => $score, 'passed' => $score >= $quiz->passing_score];
    }

    public function render()
    {
        return view('livewire.quiz-player', ['quiz' => Quiz::with('questions.options')->findOrFail($this->quizId)]);
    }
}
