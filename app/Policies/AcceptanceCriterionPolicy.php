<?php

namespace App\Policies;

use App\Models\AcceptanceCriterion;
use App\Models\User;
use App\Models\UserStory;

class AcceptanceCriterionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AcceptanceCriterion $acceptanceCriterion): bool
    {
        return $user->isMemberOf($acceptanceCriterion->userStory->project->team);
    }

    public function create(User $user, UserStory $userStory): bool
    {
        return $user->isMemberOf($userStory->project->team);
    }

    public function update(User $user, AcceptanceCriterion $acceptanceCriterion): bool
    {
        return $user->isMemberOf($acceptanceCriterion->userStory->project->team);
    }

    public function delete(User $user, AcceptanceCriterion $acceptanceCriterion): bool
    {
        return $user->isMemberOf($acceptanceCriterion->userStory->project->team);
    }
}
