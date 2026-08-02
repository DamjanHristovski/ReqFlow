<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->isOwnerOf($team);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isOwnerOf($project->team);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isOwnerOf($project->team);
    }
}
