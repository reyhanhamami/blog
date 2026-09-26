<?php

namespace App\Services\Content;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostWriter
{
    public function __construct(private HtmlSanitizer $sanitizer) {}

    public function save(?Post $post, array $data, User $actor): Post
    {
        return DB::transaction(function () use ($post, $data, $actor) {
            $creating = $post === null;
            $post ??= new Post;
            $oldSlug = $post->slug;
            $data['slug'] = Str::slug($data['slug'] ?: $data['title']);
            $data['content'] = $this->sanitizer->clean($data['content'] ?? '');
            $data['content_plain'] = trim(strip_tags($data['content']));
            if ($data['status'] === 'published') {
                $data['published_at'] = $post->published_at ?: now();
                $data['scheduled_at'] = null;
            } elseif ($data['status'] === 'scheduled') {
                $data['published_at'] = null;
            } else {
                $data['published_at'] = null;
                $data['scheduled_at'] = null;
            }
            $post->fill(collect($data)->except(['tags', 'topics', 'related_posts'])->all());
            if ($creating) {
                $post->created_by = $actor->id;
            }
            $post->updated_by = $actor->id;
            $post->save();
            $post->tags()->sync($data['tags'] ?? []);
            $post->topics()->sync($data['topics'] ?? []);
            $post->relatedPosts()->sync(array_diff($data['related_posts'] ?? [], [$post->id]));
            if ($oldSlug && $oldSlug !== $post->slug && $post->status === 'published') {
                DB::table('redirects')->updateOrInsert(['from_path' => '/'.$oldSlug], ['to_path' => '/'.$post->slug, 'status_code' => 301, 'created_at' => now(), 'updated_at' => now()]);
            }
            $version = DB::table('post_revisions')->where('post_id', $post->id)->max('version') + 1;
            DB::table('post_revisions')->insert(['post_id' => $post->id, 'user_id' => $actor->id, 'version' => $version, 'snapshot' => json_encode(array_merge($post->only(['title', 'slug', 'excerpt', 'content', 'content_plain', 'status', 'seo_title', 'seo_description', 'og_title', 'og_description', 'og_image', 'direct_answer', 'key_takeaways', 'summary', 'category_id', 'author_id', 'content_type', 'difficulty', 'featured_image', 'featured_image_alt', 'is_featured', 'noindex']), ['tags' => $post->tags()->pluck('tags.id')->all(), 'topics' => $post->topics()->pluck('topics.id')->all(), 'related_posts' => $post->relatedPosts()->pluck('posts.id')->all()])), 'summary' => $creating ? 'Artikel dibuat' : 'Artikel diperbarui', 'created_at' => now()]);
            DB::table('activities')->insert(['user_id' => $actor->id, 'action' => $creating ? 'created' : 'updated', 'subject_type' => Post::class, 'subject_id' => $post->id, 'created_at' => now()]);

            return $post;
        });
    }
}
