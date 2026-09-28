<div class="public-quiz-player">
@if($result)
<section class="public-quiz-result" aria-live="polite">
    <p class="public-kicker">HASIL KUIS</p>
    <h2>Skor: {{ $result['correct'] }} / {{ $result['total'] }}</h2>
    <p class="public-quiz-score">{{ $result['score'] }}%</p>
    <span class="public-badge">{{ $result['passed'] ? 'Lulus' : 'Belum lulus' }}</span>
    @if($courseProgress)<p>{{ $courseProgress['completed'] }} / {{ $courseProgress['total'] }} pelajaran selesai · {{ $courseProgress['total'] ? round($courseProgress['completed'] / $courseProgress['total'] * 100) : 0 }}%</p>@endif
    <ol class="public-quiz-review">@foreach($result['details'] as $detail)<li><strong>{{ $detail['question'] }} — {{ $detail['correct'] ? 'Benar' : 'Salah' }}</strong><p>Jawaban Anda: {{ implode(', ', $detail['selected']) }}</p><p>Jawaban benar: {{ implode(', ', $detail['expected']) }}</p>@if($detail['explanation'])<p>{{ $detail['explanation'] }}</p>@endif</li>@endforeach</ol>
    <button type="button" wire:click="retry" class="public-outline-button">Ulangi kuis</button>
</section>
@elseif($quiz->questions->isNotEmpty())
@php $question = $quiz->questions->values()->get($current); $total = $quiz->questions->count(); @endphp
<section class="public-panel public-quiz-question" wire:key="question-{{ $question->id }}">
    <p class="public-kicker">Pertanyaan {{ $current + 1 }} dari {{ $total }}</p>
    <div role="progressbar" aria-label="Progres kuis" aria-valuenow="{{ $current + 1 }}" aria-valuemin="1" aria-valuemax="{{ $total }}" class="public-progress"><span style="width:{{ round(($current + 1) / $total * 100) }}%"></span></div>
    <fieldset><legend>{{ $question->question }}</legend>
    @foreach($question->options as $option)<label class="public-quiz-option">
        @if($question->type === \App\Enums\QuizQuestionType::MultipleChoice)<input type="checkbox" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}">
        @else<input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}">@endif
        <span>{{ $option->label }}</span>
    </label>@endforeach
    </fieldset>
    @error('answers')<p class="error" role="alert">{{ $message }}</p>@enderror
    <div class="public-form-actions">@if($current > 0)<button type="button" wire:click="previous" class="public-outline-button">← Sebelumnya</button>@endif
    @if($current < $total - 1)<button type="button" wire:click="next" class="public-button is-gold">Berikutnya →</button>
    @else<button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="public-button is-gold">Kirim jawaban →</button>@endif</div>
</section>
@else<div class="public-empty"><p>Pertanyaan belum tersedia.</p></div>@endif
</div>
