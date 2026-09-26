<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function store(Request $request, Post $post)
    {
        abort_unless($post->status === 'published' && $post->published_at?->isPast(), 404);
        $data = $request->validate(['name' => [$request->user() ? 'nullable' : 'required', 'string', 'max:191'], 'email' => [$request->user() ? 'nullable' : 'required', 'email', 'max:191'], 'body' => ['required', 'string', 'min:10', 'max:2000']]);
        Question::create(['post_id' => $post->id, 'user_id' => $request->user()?->id, 'name' => $request->user()?->name ?? $data['name'], 'email' => $request->user()?->email ?? $data['email'], 'body' => strip_tags($data['body']), 'status' => 'pending']);

        return back()->with('success', 'Pertanyaan dikirim dan menunggu moderasi.');
    }
}
