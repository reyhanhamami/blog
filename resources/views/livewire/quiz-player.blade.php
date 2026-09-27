<div>
@if($result)
<section class="mt-8 rounded-xl bg-indigo-50 p-6" aria-live="polite">
    <h2 class="text-2xl font-bold">Skor: {{ $result['correct'] }} / {{ $result['total'] }} ({{ $result['score'] }}%)</h2>
    <p class="mt-2 font-semibold">Status: {{ $result['passed'] ? 'Lulus' : 'Belum lulus' }}</p>
    @if($courseProgress)<p class="mt-2">{{ $courseProgress['completed'] }} / {{ $courseProgress['total'] }} pelajaran selesai · {{ $courseProgress['total'] ? round($courseProgress['completed'] / $courseProgress['total'] * 100) : 0 }}%</p>@endif
    <ol class="mt-5 space-y-4">
        @foreach($result['details'] as $detail)
        <li><p class="font-semibold">{{ $detail['question'] }} — {{ $detail['correct'] ? 'Benar' : 'Salah' }}</p>
            <p>Jawaban Anda: {{ implode(', ', $detail['selected']) }}</p>
            <p>Jawaban benar: {{ implode(', ', $detail['expected']) }}</p>
            @if($detail['explanation'])<p class="text-sm">{{ $detail['explanation'] }}</p>@endif
        </li>
        @endforeach
    </ol>
    <button type="button" wire:click="retry" class="btn-secondary mt-4">Ulangi kuis</button>
</section>
@elseif($quiz->questions->isNotEmpty())
@php $question = $quiz->questions->values()->get($current); $total = $quiz->questions->count(); @endphp
<section class="mt-8 card" wire:key="question-{{ $question->id }}">
    <p class="text-sm font-semibold">Pertanyaan {{ $current + 1 }} dari {{ $total }}</p>
    <div role="progressbar" aria-label="Progres kuis" aria-valuenow="{{ $current + 1 }}" aria-valuemin="1" aria-valuemax="{{ $total }}" class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full bg-indigo-600" style="width:{{ round(($current + 1) / $total * 100) }}%"></div></div>
    <fieldset class="mt-6"><legend class="mb-4 font-bold">{{ $question->question }}</legend>
    @foreach($question->options as $option)
    <label class="mb-2 flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-3">
        @if($question->type === \App\Enums\QuizQuestionType::MultipleChoice)
        <input type="checkbox" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}">
        @else
        <input type="radio" wire:model="answers.{{ $question->id }}" value="{{ $option->id }}">
        @endif
        <span>{{ $option->label }}</span>
    </label>
    @endforeach
    </fieldset>
    @error('answers')<p class="error" role="alert">{{ $message }}</p>@enderror
    <div class="mt-5 flex gap-3">
        @if($current > 0)<button type="button" wire:click="previous" class="btn-secondary">Sebelumnya</button>@endif
        @if($current < $total - 1)<button type="button" wire:click="next" class="btn-primary">Berikutnya</button>
        @else<button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="btn-primary">Kirim jawaban</button>@endif
    </div>
</section>
@else<p class="mt-8 rounded-lg bg-slate-50 p-6">Pertanyaan belum tersedia.</p>@endif
</div>