<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Specification;
use App\Models\User;

class SpecificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Specification $specification): bool
    {
        return $user->isMemberOf($specification->project->team);
    }

    public function create(User $user, Project $project): bool
    {
        return $user->isMemberOf($project->team);
    }

    public function update(User $user, Specification $specification): bool
    {
        return $user->isMemberOf($specification->project->team);
    }

    public function delete(User $user, Specification $specification): bool
    {
        return $user->isMemberOf($specification->project->team);
    }
}
