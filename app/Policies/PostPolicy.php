<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('articles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('articles.create');
    }

    public function view(User $user, Post $post): bool
    {
        return $this->update($user, $post) || ($user->can('articles.view') && ! $user->can('articles.update-own'));
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('articles.update') || ($user->can('articles.update-own') && $post->created_by === $user->id);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('articles.delete') || ($user->can('articles.delete-own') && $post->created_by === $user->id);
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->can('articles.restore');
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->can('articles.force-delete');
    }

    public function publish(User $user, Post $post): bool
    {
        return $user->can('articles.publish');
    }
}
