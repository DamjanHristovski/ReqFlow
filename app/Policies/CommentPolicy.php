<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Specification;
use App\Models\User;

class CommentPolicy
{
    public function create(User $user, Specification $specification): bool
    {
        return $user->isMemberOf($specification->project->team);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }
}
