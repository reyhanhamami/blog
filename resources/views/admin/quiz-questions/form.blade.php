@extends('admin.layout')
@section('title', $question->exists ? 'Edit Pertanyaan' : 'Buat Pertanyaan')
@section('content')
<a wire:navigate href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="text-sm text-indigo-700">← Kembali</a>
<h1 class="my-6 text-3xl font-bold">{{ $question->exists ? 'Edit' : 'Buat' }} pertanyaan</h1>
@php
$type = old('type', $question->type?->value ?? \App\Enums\QuizQuestionType::SingleChoice->value);
$existing = $question->exists ? $question->options->values() : collect();
$correct = old('correct', $existing->search(fn ($option) => $option->is_correct));
$correctOptions = old('correct_options', $existing->filter(fn ($option) => $option->is_correct)->keys()->all());
$correctBoolean = old('correct_boolean', $existing->firstWhere('is_correct', true)?->label === 'Salah' ? 'false' : 'true');
@endphp
<form method="post" action="{{ $question->exists ? route('admin.quizzes.questions.update', [$quiz, $question]) : route('admin.quizzes.questions.store', $quiz) }}" class="card max-w-3xl space-y-5" x-data="{ type: @js($type) }">
@csrf @if($question->exists) @method('PATCH') @endif
<div><label class="form-label" for="question">Pertanyaan *</label><textarea class="form-input" id="question" name="question" required rows="3">{{ old('question', $question->question) }}</textarea>@error('question')<p class="error">{{ $message }}</p>@enderror</div>
<div><label class="form-label" for="type">Jenis pertanyaan *</label><select class="form-input" id="type" name="type" x-model="type">
<option value="{{ \App\Enums\QuizQuestionType::SingleChoice->value }}" @selected($type === \App\Enums\QuizQuestionType::SingleChoice->value)>Satu jawaban</option>
<option value="{{ \App\Enums\QuizQuestionType::MultipleChoice->value }}" @selected($type === \App\Enums\QuizQuestionType::MultipleChoice->value)>Beberapa jawaban</option>
<option value="{{ \App\Enums\QuizQuestionType::TrueFalse->value }}" @selected($type === \App\Enums\QuizQuestionType::TrueFalse->value)>Benar / Salah</option>
</select></div>
<div class="grid gap-4 sm:grid-cols-2"><div><label class="form-label">Urutan</label><input class="form-input" type="number" name="sort_order" min="0" value="{{ old('sort_order', $question->sort_order ?? 0) }}"></div><div><label class="form-label">Poin</label><input class="form-input" type="number" name="points" min="1" max="100" value="{{ old('points', $question->points ?? 1) }}"></div></div>
<fieldset x-show="type !== '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'"><legend class="mb-3 font-semibold">Pilihan jawaban</legend><div class="mb-3 flex items-center gap-3">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::SingleChoice->value }}'" type="radio" name="correct" value="0" @checked((string)$correct === '0') aria-label="Jawaban benar pilihan 1">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::MultipleChoice->value }}'" type="checkbox" name="correct_options[]" value="0" @checked(in_array(0, $correctOptions)) aria-label="Jawaban benar pilihan 1">
<input class="form-input" name="options[0]" x-bind:disabled="type === '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'" placeholder="Pilihan 1" value="{{ old('options.0', $existing->get(0)?->label) }}">
</div><div class="mb-3 flex items-center gap-3">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::SingleChoice->value }}'" type="radio" name="correct" value="1" @checked((string)$correct === '1') aria-label="Jawaban benar pilihan 2">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::MultipleChoice->value }}'" type="checkbox" name="correct_options[]" value="1" @checked(in_array(1, $correctOptions)) aria-label="Jawaban benar pilihan 2">
<input class="form-input" name="options[1]" x-bind:disabled="type === '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'" placeholder="Pilihan 2" value="{{ old('options.1', $existing->get(1)?->label) }}">
</div><div class="mb-3 flex items-center gap-3">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::SingleChoice->value }}'" type="radio" name="correct" value="2" @checked((string)$correct === '2') aria-label="Jawaban benar pilihan 3">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::MultipleChoice->value }}'" type="checkbox" name="correct_options[]" value="2" @checked(in_array(2, $correctOptions)) aria-label="Jawaban benar pilihan 3">
<input class="form-input" name="options[2]" x-bind:disabled="type === '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'" placeholder="Pilihan 3" value="{{ old('options.2', $existing->get(2)?->label) }}">
</div><div class="mb-3 flex items-center gap-3">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::SingleChoice->value }}'" type="radio" name="correct" value="3" @checked((string)$correct === '3') aria-label="Jawaban benar pilihan 4">
<input x-show="type === '{{ \App\Enums\QuizQuestionType::MultipleChoice->value }}'" type="checkbox" name="correct_options[]" value="3" @checked(in_array(3, $correctOptions)) aria-label="Jawaban benar pilihan 4">
<input class="form-input" name="options[3]" x-bind:disabled="type === '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'" placeholder="Pilihan 4" value="{{ old('options.3', $existing->get(3)?->label) }}">
</div><p class="text-xs text-slate-500">Isi minimal dua pilihan dan tandai jawaban benar.</p>
@error('options')<p class="error">{{ $message }}</p>@enderror @error('correct')<p class="error">{{ $message }}</p>@enderror
</fieldset>
<fieldset x-show="type === '{{ \App\Enums\QuizQuestionType::TrueFalse->value }}'"><legend class="mb-3 font-semibold">Jawaban benar</legend>
<label class="mr-5"><input type="radio" name="correct_boolean" value="true" @checked($correctBoolean === 'true')> Benar</label>
<label><input type="radio" name="correct_boolean" value="false" @checked($correctBoolean === 'false')> Salah</label>
@error('correct_boolean')<p class="error">{{ $message }}</p>@enderror
</fieldset>
<div><label class="form-label" for="explanation">Penjelasan jawaban</label><textarea class="form-input" id="explanation" name="explanation" rows="4">{{ old('explanation', $question->explanation) }}</textarea></div>
<div class="flex gap-3 border-t border-slate-100 pt-5"><button class="btn-primary">Simpan pertanyaan</button><a wire:navigate href="{{ route('admin.quizzes.questions.index', $quiz) }}" class="btn-secondary">Batal</a></div>
</form>
@endsection