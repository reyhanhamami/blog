<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function before(User $user): ?bool
    {
        return $user->role === 'superadmin' ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canAccessCms();
    }

    public function create(User $user): bool
    {
        return $user->canAccessCms();
    }

    public function view(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function update(User $user, Post $post): bool
    {
        return in_array($user->role, ['admin', 'editor'], true) || ($user->role === 'author' && $post->created_by === $user->id);
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->update($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $user->role === 'admin';
    }

    public function publish(User $user, Post $post): bool
    {
        return in_array($user->role, ['admin', 'editor'], true);
    }
}
