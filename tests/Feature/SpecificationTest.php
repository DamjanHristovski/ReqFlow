<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Specification;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();

        $this->get(route('projects.specifications.create', $project))->assertRedirect(route('login'));
    }

    public function test_member_can_create_a_specification(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('projects.specifications.store', $project), [
            'title' => 'Login Flow',
            'description' => 'Users can authenticate.',
        ]);

        $specification = Specification::firstWhere('title', 'Login Flow');

        $response->assertRedirect(route('specifications.show', $specification));
        $this->assertNotNull($specification);
        $this->assertSame($project->id, $specification->project_id);
        $this->assertSame(1, $specification->current_version);
    }

    public function test_owner_can_create_a_specification(): void
    {
        [, $owner, , $project] = $this->createProjectWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('projects.specifications.store', $project), ['title' => 'Login Flow'])
            ->assertRedirect();
    }

    public function test_outsider_cannot_create_a_specification(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('projects.specifications.store', $project), ['title' => 'Login Flow'])
            ->assertForbidden();
    }

    public function test_specification_creation_requires_a_title(): void
    {
        [, $owner, , $project] = $this->createProjectWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('projects.specifications.store', $project), [])
            ->assertSessionHasErrors('title');
    }

    public function test_member_can_view_a_specification(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)->get(route('specifications.show', $specification))->assertOk();
    }

    public function test_outsider_cannot_view_a_specification(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('specifications.show', $specification))->assertForbidden();
    }

    public function test_member_can_update_a_specification(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->patch(route('specifications.update', $specification), ['title' => 'Renamed'])
            ->assertRedirect(route('specifications.show', $specification));

        $this->assertSame('Renamed', $specification->fresh()->title);
    }

    public function test_outsider_cannot_update_a_specification(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('specifications.update', $specification), ['title' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_member_can_delete_a_specification(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->delete(route('specifications.destroy', $specification))
            ->assertRedirect(route('projects.show', $project));

        $this->assertSoftDeleted($specification);
    }

    public function test_outsider_cannot_delete_a_specification(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->delete(route('specifications.destroy', $specification))->assertForbidden();
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Project}
     */
    private function createProjectWithOwnerAndMember(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['created_by' => $owner->id]);
        $team->teamMembers()->create(['user_id' => $owner->id, 'role' => TeamMember::ROLE_OWNER]);

        $member = User::factory()->create();
        $team->teamMembers()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER]);

        $project = Project::factory()->create(['team_id' => $team->id]);

        return [$team, $owner, $member, $project];
    }
}
