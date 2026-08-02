<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddTeamMemberRequest;
use App\Http\Requests\UpdateTeamMemberRoleRequest;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TeamMemberController extends Controller
{
    public function store(AddTeamMemberRequest $request, Team $team)
    {
        $user = User::where('email', $request->validated('email'))->firstOrFail();

        if ($team->members()->where('users.id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the team.',
            ]);
        }

        $team->teamMembers()->create([
            'user_id' => $user->id,
            'role' => TeamMember::ROLE_MEMBER,
        ]);

        return back()->with('status', "{$user->name} added to the team.");
    }

    public function update(UpdateTeamMemberRoleRequest $request, Team $team, User $member)
    {
        $teamMember = $team->teamMembers()->where('user_id', $member->id)->firstOrFail();

        $this->guardLastOwner($team, $teamMember, $request->validated('role'));

        $teamMember->update(['role' => $request->validated('role')]);

        return back()->with('status', "{$member->name}'s role updated.");
    }

    public function destroy(Team $team, User $member)
    {
        $teamMember = $team->teamMembers()->where('user_id', $member->id)->firstOrFail();

        $isSelf = Auth::id() === $member->id;

        if (! $isSelf) {
            $this->authorize('removeMember', $team);
        }

        $this->guardLastOwner($team, $teamMember, TeamMember::ROLE_MEMBER);

        $teamMember->delete();

        return $isSelf
            ? redirect()->route('teams.index')->with('status', 'You left the team.')
            : back()->with('status', "{$member->name} removed from the team.");
    }

    private function guardLastOwner(Team $team, TeamMember $teamMember, string $newRole): void
    {
        if ($teamMember->isOwner() && $newRole !== TeamMember::ROLE_OWNER) {
            $remainingOwners = $team->teamMembers()
                ->where('role', TeamMember::ROLE_OWNER)
                ->where('id', '!=', $teamMember->id)
                ->count();

            if ($remainingOwners === 0) {
                throw ValidationException::withMessages([
                    'role' => 'A team must have at least one owner.',
                ]);
            }
        }
    }
}
