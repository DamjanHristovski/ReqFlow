<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('teams.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_a_team_and_becomes_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('teams.store'), [
            'name' => 'Acme Inc',
        ]);

        $team = Team::firstWhere('name', 'Acme Inc');

        $response->assertRedirect(route('teams.show', $team));
        $this->assertNotNull($team);
        $this->assertTrue($user->isOwnerOf($team));
    }

    public function test_team_creation_requires_a_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('teams.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_member_can_view_their_team(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)->get(route('teams.show', $team))->assertOk();
    }

    public function test_non_member_cannot_view_team(): void
    {
        [$team] = $this->createTeamWithOwner();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('teams.show', $team))->assertForbidden();
    }

    public function test_owner_can_update_team_name(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->patch(route('teams.update', $team), ['name' => 'Renamed'])
            ->assertRedirect(route('teams.show', $team));

        $this->assertSame('Renamed', $team->fresh()->name);
    }

    public function test_member_cannot_update_team_name(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($member)
            ->patch(route('teams.update', $team), ['name' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_team(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->delete(route('teams.destroy', $team))
            ->assertRedirect(route('teams.index'));

        $this->assertSoftDeleted($team);
    }

    public function test_member_cannot_delete_team(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($member)->delete(route('teams.destroy', $team))->assertForbidden();
    }

    public function test_owner_can_add_member_by_email(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();
        $newUser = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), ['email' => $newUser->email])
            ->assertRedirect();

        $this->assertTrue($newUser->fresh()->isMemberOf($team));
    }

    public function test_adding_nonexistent_email_fails_validation(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), ['email' => 'nobody@example.com'])
            ->assertSessionHasErrors('email');
    }

    public function test_adding_existing_member_fails_validation(): void
    {
        [$team, $owner, $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('teams.members.store', $team), ['email' => $member->email])
            ->assertSessionHasErrors('email');
    }

    public function test_member_cannot_add_members(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();
        $newUser = User::factory()->create();

        $this->actingAs($member)
            ->post(route('teams.members.store', $team), ['email' => $newUser->email])
            ->assertForbidden();
    }

    public function test_owner_can_remove_a_member(): void
    {
        [$team, $owner, $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $member]))
            ->assertRedirect();

        $this->assertFalse($member->fresh()->isMemberOf($team));
    }

    public function test_member_can_leave_team(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($member)
            ->delete(route('teams.members.destroy', [$team, $member]))
            ->assertRedirect(route('teams.index'));

        $this->assertFalse($member->fresh()->isMemberOf($team));
    }

    public function test_member_cannot_remove_another_member(): void
    {
        [$team, $owner, $member] = $this->createTeamWithOwnerAndMember();
        $anotherOwner = User::factory()->create();
        $team->teamMembers()->create(['user_id' => $anotherOwner->id, 'role' => TeamMember::ROLE_MEMBER]);

        $this->actingAs($member)
            ->delete(route('teams.members.destroy', [$team, $anotherOwner]))
            ->assertForbidden();
    }

    public function test_owner_can_change_member_role(): void
    {
        [$team, $owner, $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $member]), ['role' => TeamMember::ROLE_OWNER])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->isOwnerOf($team));
    }

    public function test_last_owner_cannot_be_demoted(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->patch(route('teams.members.update', [$team, $owner]), ['role' => TeamMember::ROLE_MEMBER])
            ->assertSessionHasErrors('role');

        $this->assertTrue($owner->fresh()->isOwnerOf($team));
    }

    public function test_last_owner_cannot_leave_team(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->delete(route('teams.members.destroy', [$team, $owner]))
            ->assertSessionHasErrors('role');

        $this->assertTrue($owner->fresh()->isMemberOf($team));
    }

    /**
     * @return array{0: Team, 1: User}
     */
    private function createTeamWithOwner(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['created_by' => $owner->id]);
        $team->teamMembers()->create(['user_id' => $owner->id, 'role' => TeamMember::ROLE_OWNER]);

        return [$team, $owner];
    }

    /**
     * @return array{0: Team, 1: User, 2: User}
     */
    private function createTeamWithOwnerAndMember(): array
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $member = User::factory()->create();
        $team->teamMembers()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER]);

        return [$team, $owner, $member];
    }
}
