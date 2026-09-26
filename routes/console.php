<?php

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Post::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('id')->chunkById(100, function ($posts) {
        foreach ($posts as $post) {
            DB::transaction(function () use ($post) {
                $post->update(['status' => 'published', 'published_at' => $post->scheduled_at, 'scheduled_at' => null]);
                DB::table('activities')->insert(['user_id' => null, 'action' => 'published', 'subject_type' => Post::class, 'subject_id' => $post->id, 'created_at' => now()]);
            });
        }
    });
})->everyMinute()->name('publish-scheduled-posts')->withoutOverlapping();
