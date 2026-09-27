<?php

namespace App\Services;

use App\Models\LearningPathItem;
use App\Models\Post;

class ArticleNavigation
{
    public function for(Post $post): array
    {
        $item = LearningPathItem::where('post_id', $post->id)
            ->whereHas('path', fn ($q) => $q->where('status', 'published'))
            ->orderBy('learning_path_id')->first();
        if ($item) {
            $siblings = LearningPathItem::where('learning_path_id', $item->learning_path_id)
                ->whereHas('post', fn ($q) => $q->published()->where('noindex', false));
            $previous = (clone $siblings)->where(fn ($q) => $q->where('sort_order', '<', $item->sort_order)
                ->orWhere(fn ($q) => $q->where('sort_order', $item->sort_order)->where('id', '<', $item->id)))
                ->orderByDesc('sort_order')->orderByDesc('id')->with('post')->first()?->post;
            $next = (clone $siblings)->where(fn ($q) => $q->where('sort_order', '>', $item->sort_order)
                ->orWhere(fn ($q) => $q->where('sort_order', $item->sort_order)->where('id', '>', $item->id)))
                ->orderBy('sort_order')->orderBy('id')->with('post')->first()?->post;

            return compact('previous', 'next');
        }

        $topicId = $post->topics->sortBy('id')->first()?->id;
        $find = function (bool $older) use ($post, $topicId): ?Post {
            foreach (['topic', 'category', 'all'] as $priority) {
                if ($priority === 'topic' && ! $topicId || $priority === 'category' && ! $post->category_id) {
                    continue;
                }
                $query = Post::published()->where('noindex', false)->whereKeyNot($post->id)
                    ->where(fn ($q) => $q->where('published_at', $older ? '<' : '>', $post->published_at)
                        ->orWhere(fn ($q) => $q->where('published_at', $post->published_at)->where('id', $older ? '<' : '>', $post->id)));
                if ($priority === 'topic') {
                    $query->whereHas('topics', fn ($q) => $q->whereKey($topicId));
                } elseif ($priority === 'category') {
                    $query->where('category_id', $post->category_id);
                }
                $candidate = $query->orderBy('published_at', $older ? 'desc' : 'asc')
                    ->orderBy('id', $older ? 'desc' : 'asc')->first();
                if ($candidate) {
                    return $candidate;
                }
            }

            return null;
        };

        return ['previous' => $find(true), 'next' => $find(false)];
    }
}
