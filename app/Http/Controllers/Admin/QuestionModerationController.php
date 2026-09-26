<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuestionModerationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-content');
        $questions = Question::with('post')->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))->latest()->paginate(20)->withQueryString();

        return view('admin.questions.index', compact('questions'));
    }

    public function show(Question $question)
    {
        Gate::authorize('manage-content');

        return view('admin.questions.show', ['question' => $question->load(['post', 'answers.user'])]);
    }

    public function update(Request $request, Question $question)
    {
        Gate::authorize('manage-content');
        $question->update($request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'spam'])]]));

        return back()->with('success', 'Status pertanyaan diperbarui.');
    }

    public function answer(Request $request, Question $question)
    {
        Gate::authorize('manage-content');
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:5000']]);
        $question->answers()->create(['user_id' => $request->user()->id, 'body' => strip_tags($data['body'])]);
        $question->update(['status' => 'approved']);

        return back()->with('success', 'Jawaban dikirim.');
    }

    public function destroy(Question $question)
    {
        Gate::authorize('manage-content');
        $question->delete();

        return redirect()->route('admin.questions.index')->with('success', 'Pertanyaan dihapus.');
    }
}
