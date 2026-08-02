<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Specification;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecificationVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_specification_creates_version_one(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();

        $this->actingAs($member)->post(route('projects.specifications.store', $project), [
            'title' => 'Login Flow',
        ]);

        $specification = Specification::firstWhere('title', 'Login Flow');

        $this->assertSame(1, $specification->current_version);
        $this->assertSame(1, $specification->versions()->count());
        $this->assertSame('Login Flow', $specification->versions()->first()->content['title']);
    }

    public function test_editing_with_changed_content_creates_a_new_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id, 'current_version' => 1]);
        $this->recordInitialVersion($specification);

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'Renamed Title',
        ]);

        $specification->refresh();

        $this->assertSame(2, $specification->current_version);
        $this->assertSame(2, $specification->versions()->count());
        $this->assertSame('Renamed Title', $specification->versions()->where('version_number', 2)->first()->content['title']);
    }

    public function test_editing_without_changes_does_not_create_a_new_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create([
            'project_id' => $project->id,
            'title' => 'Same Title',
            'current_version' => 1,
        ]);
        $this->recordInitialVersion($specification);

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'Same Title',
            'description' => $specification->description,
            'goals' => $specification->goals,
            'scope' => $specification->scope,
            'functional_requirements' => $specification->functional_requirements,
            'non_functional_requirements' => $specification->non_functional_requirements,
        ]);

        $specification->refresh();

        $this->assertSame(1, $specification->current_version);
        $this->assertSame(1, $specification->versions()->count());
    }

    public function test_outsider_cannot_view_version_history(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $this->recordInitialVersion($specification);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('specifications.versions.index', $specification))
            ->assertForbidden();
    }

    public function test_member_can_view_version_history(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $this->recordInitialVersion($specification);

        $this->actingAs($member)
            ->get(route('specifications.versions.index', $specification))
            ->assertOk();
    }

    public function test_member_can_view_a_single_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $version = $this->recordInitialVersion($specification);

        $this->actingAs($member)
            ->get(route('specifications.versions.show', [$specification, $version]))
            ->assertOk();
    }

    public function test_member_can_compare_two_versions(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id, 'title' => 'V1 Title']);
        $this->recordInitialVersion($specification);

        $specification->update(['title' => 'V2 Title']);
        $specification->update(['current_version' => 2]);
        $specification->versions()->create([
            'version_number' => 2,
            'content' => app(\App\Services\SpecificationVersionService::class)->snapshot($specification),
            'changed_by' => $member->id,
        ]);

        $response = $this->actingAs($member)->get(route('specifications.versions.compare', [
            'specification' => $specification,
            'from' => 1,
            'to' => 2,
        ]));

        $response->assertOk();
        $response->assertSee('V1 Title');
        $response->assertSee('V2 Title');
    }

    public function test_restore_rewinds_the_pointer_without_creating_a_new_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id, 'title' => 'Original Title']);
        $originalVersion = $this->recordInitialVersion($specification);

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'Changed Title',
        ]);

        $this->actingAs($member)
            ->post(route('specifications.versions.restore', [$specification, $originalVersion]))
            ->assertRedirect(route('specifications.show', $specification));

        $specification->refresh();

        $this->assertSame('Original Title', $specification->title);
        $this->assertSame(1, $specification->current_version);
        $this->assertSame(2, $specification->versions()->count());
        $this->assertSame('Changed Title', $specification->versions()->where('version_number', 2)->first()->content['title']);
    }

    public function test_editing_after_a_restore_skips_the_rewound_past_version_number(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id, 'title' => 'V1']);
        $v1 = $this->recordInitialVersion($specification);

        $this->actingAs($member)->patch(route('specifications.update', $specification), ['title' => 'V2']);
        $this->actingAs($member)->post(route('specifications.versions.restore', [$specification, $v1]));

        $this->actingAs($member)->patch(route('specifications.update', $specification), ['title' => 'V3 (new content)']);

        $specification->refresh();

        $this->assertSame(3, $specification->current_version);
        $this->assertSame(3, $specification->versions()->count());
        $this->assertNotNull($specification->versions()->where('version_number', 2)->first());
    }

    public function test_editing_to_match_an_earlier_version_prompts_instead_of_saving(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create([
            'project_id' => $project->id,
            'title' => 'V1',
            'description' => 'fixed description',
            'goals' => null,
            'scope' => null,
            'functional_requirements' => null,
            'non_functional_requirements' => null,
        ]);
        $this->recordInitialVersion($specification);
        $fields = ['description' => 'fixed description', 'goals' => '', 'scope' => '', 'functional_requirements' => '', 'non_functional_requirements' => ''];

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'V2',
            ...$fields,
        ]);

        $response = $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'V1',
            ...$fields,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('matched_version_number', 1);

        $specification->refresh();
        $this->assertSame('V2', $specification->title);
        $this->assertSame(2, $specification->versions()->count());
    }

    public function test_force_new_version_bypasses_the_match_prompt(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create([
            'project_id' => $project->id,
            'title' => 'V1',
            'description' => 'fixed description',
            'goals' => null,
            'scope' => null,
            'functional_requirements' => null,
            'non_functional_requirements' => null,
        ]);
        $this->recordInitialVersion($specification);
        $fields = ['description' => 'fixed description', 'goals' => '', 'scope' => '', 'functional_requirements' => '', 'non_functional_requirements' => ''];

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'V2',
            ...$fields,
        ]);

        $this->actingAs($member)->patch(route('specifications.update', $specification), [
            'title' => 'V1',
            ...$fields,
            'force_new_version' => '1',
        ]);

        $specification->refresh();

        $this->assertSame('V1', $specification->title);
        $this->assertSame(3, $specification->current_version);
        $this->assertSame(3, $specification->versions()->count());
    }

    public function test_outsider_cannot_restore_a_version(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $specification = Specification::factory()->create(['project_id' => $project->id]);
        $version = $this->recordInitialVersion($specification);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('specifications.versions.restore', [$specification, $version]))
            ->assertForbidden();
    }

    private function recordInitialVersion(Specification $specification): \App\Models\SpecificationVersion
    {
        return app(\App\Services\SpecificationVersionService::class)
            ->recordInitialVersion($specification, $specification->creator ?? User::factory()->create());
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
