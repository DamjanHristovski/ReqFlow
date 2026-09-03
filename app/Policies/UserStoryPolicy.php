<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\UserStory;

class UserStoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, UserStory $userStory): bool
    {
        return $user->isMemberOf($userStory->project->team);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->team);
    }

    public function update(User $user, UserStory $userStory): bool
    {
        return $user->isMemberOf($userStory->project->team);
    }

    public function delete(User $user, UserStory $userStory): bool
    {
        return $user->isMemberOf($userStory->project->team);
    }
}
