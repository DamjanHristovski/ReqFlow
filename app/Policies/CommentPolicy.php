<?php

namespace App\Policies;

use App\Models\AcceptanceCriterion;
use App\Models\Comment;
use App\Models\Specification;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStory;

class CommentPolicy
{
    public function create(User $user, Specification|UserStory|AcceptanceCriterion $commentable): bool
    {
        return $user->isMemberOf($this->teamFor($commentable));
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $user->id === $comment->user_id;
    }

    private function teamFor(Specification|UserStory|AcceptanceCriterion $commentable): Team
    {
        return match (true) {
            $commentable instanceof Specification => $commentable->project->team,
            $commentable instanceof UserStory => $commentable->project->team,
            $commentable instanceof AcceptanceCriterion => $commentable->userStory->project->team,
        };
    }
}
