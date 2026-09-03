<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [$team] = $this->createTeamWithOwner();

        $this->get(route('teams.projects.index', $team))->assertRedirect(route('login'));
    }

    public function test_owner_can_create_a_project(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $response = $this->actingAs($owner)->post(route('teams.projects.store', $team), [
            'name' => 'New Website',
            'description' => 'Rebuild the marketing site',
            'status' => Project::STATUS_PLANNING,
        ]);

        $project = Project::firstWhere('name', 'New Website');

        $response->assertRedirect(route('projects.show', $project));
        $this->assertNotNull($project);
        $this->assertSame($team->id, $project->team_id);
    }

    public function test_member_cannot_create_a_project(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();

        $this->actingAs($member)
            ->post(route('teams.projects.store', $team), [
                'name' => 'New Website',
                'status' => Project::STATUS_PLANNING,
            ])
            ->assertForbidden();
    }

    public function test_outsider_cannot_create_a_project(): void
    {
        [$team] = $this->createTeamWithOwner();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('teams.projects.store', $team), [
                'name' => 'New Website',
                'status' => Project::STATUS_PLANNING,
            ])
            ->assertForbidden();
    }

    public function test_project_creation_requires_a_name(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->post(route('teams.projects.store', $team), ['status' => Project::STATUS_PLANNING])
            ->assertSessionHasErrors('name');
    }

    public function test_project_creation_requires_a_valid_status(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();

        $this->actingAs($owner)
            ->post(route('teams.projects.store', $team), ['name' => 'X', 'status' => 'not-a-status'])
            ->assertSessionHasErrors('status');
    }

    public function test_member_can_view_a_project(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)->get(route('projects.show', $project))->assertOk();
    }

    public function test_non_member_cannot_view_a_project(): void
    {
        [$team] = $this->createTeamWithOwner();
        $project = Project::factory()->create(['team_id' => $team->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_owner_can_update_a_project(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)
            ->patch(route('projects.update', $project), [
                'name' => 'Renamed',
                'status' => Project::STATUS_IN_PROGRESS,
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('Renamed', $project->fresh()->name);
    }

    public function test_member_cannot_update_a_project(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)
            ->patch(route('projects.update', $project), [
                'name' => 'Renamed',
                'status' => Project::STATUS_IN_PROGRESS,
            ])
            ->assertForbidden();
    }

    public function test_owner_can_delete_a_project(): void
    {
        [$team, $owner] = $this->createTeamWithOwner();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $this->actingAs($owner)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('teams.projects.index', $team));

        $this->assertSoftDeleted($project);
    }

    public function test_member_cannot_delete_a_project(): void
    {
        [$team, , $member] = $this->createTeamWithOwnerAndMember();
        $project = Project::factory()->create(['team_id' => $team->id]);

        $this->actingAs($member)->delete(route('projects.destroy', $project))->assertForbidden();
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
