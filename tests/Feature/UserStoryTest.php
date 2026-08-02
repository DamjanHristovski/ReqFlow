<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserStory;
use App\Services\SpecificationVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();

        $this->get(route('projects.user-stories.create', $project))->assertRedirect(route('login'));
    }

    public function test_member_can_create_a_user_story(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('projects.user-stories.store', $project), [
            'title' => 'As a user, I can log in',
            'description' => 'Users can authenticate.',
        ]);

        $userStory = UserStory::firstWhere('title', 'As a user, I can log in');

        $response->assertRedirect(route('user-stories.show', $userStory));
        $this->assertNotNull($userStory);
        $this->assertSame($project->id, $userStory->project_id);
        $this->assertSame(1, $userStory->current_version);
    }

    public function test_owner_can_create_a_user_story(): void
    {
        [, $owner, , $project] = $this->createProjectWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('projects.user-stories.store', $project), ['title' => 'Login Flow'])
            ->assertRedirect();
    }

    public function test_outsider_cannot_create_a_user_story(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('projects.user-stories.store', $project), ['title' => 'Login Flow'])
            ->assertForbidden();
    }

    public function test_user_story_creation_requires_a_title(): void
    {
        [, $owner, , $project] = $this->createProjectWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('projects.user-stories.store', $project), [])
            ->assertSessionHasErrors('title');
    }

    public function test_member_can_view_a_user_story(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)->get(route('user-stories.show', $userStory))->assertOk();
    }

    public function test_outsider_cannot_view_a_user_story(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('user-stories.show', $userStory))->assertForbidden();
    }

    public function test_member_can_update_a_user_story(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->patch(route('user-stories.update', $userStory), ['title' => 'Renamed'])
            ->assertRedirect(route('user-stories.show', $userStory));

        $this->assertSame('Renamed', $userStory->fresh()->title);
    }

    public function test_outsider_cannot_update_a_user_story(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('user-stories.update', $userStory), ['title' => 'Renamed'])
            ->assertForbidden();
    }

    public function test_member_can_delete_a_user_story(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        $this->actingAs($member)
            ->delete(route('user-stories.destroy', $userStory))
            ->assertRedirect(route('projects.show', $project));

        $this->assertSoftDeleted($userStory);
    }

    public function test_outsider_cannot_delete_a_user_story(): void
    {
        [, , , $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->delete(route('user-stories.destroy', $userStory))->assertForbidden();
    }

    public function test_editing_with_changed_content_creates_a_new_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id, 'current_version' => 1]);
        app(SpecificationVersionService::class)->recordInitialVersion($userStory, $member);

        $this->actingAs($member)->patch(route('user-stories.update', $userStory), [
            'title' => 'Renamed Title',
        ]);

        $userStory->refresh();

        $this->assertSame(2, $userStory->current_version);
        $this->assertSame(2, $userStory->versions()->count());
        $this->assertSame('Renamed Title', $userStory->versions()->where('version_number', 2)->first()->content['title']);
    }

    public function test_member_can_view_version_history(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);
        app(SpecificationVersionService::class)->recordInitialVersion($userStory, $member);

        $this->actingAs($member)
            ->get(route('user-stories.versions.index', $userStory))
            ->assertOk();
    }

    public function test_restore_rewinds_the_pointer_without_creating_a_new_version(): void
    {
        [, , $member, $project] = $this->createProjectWithOwnerAndMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id, 'title' => 'Original Title']);
        $originalVersion = app(SpecificationVersionService::class)->recordInitialVersion($userStory, $member);

        $this->actingAs($member)->patch(route('user-stories.update', $userStory), [
            'title' => 'Changed Title',
        ]);

        $this->actingAs($member)
            ->post(route('user-stories.versions.restore', [$userStory, $originalVersion]))
            ->assertRedirect(route('user-stories.show', $userStory));

        $userStory->refresh();

        $this->assertSame('Original Title', $userStory->title);
        $this->assertSame(1, $userStory->current_version);
        $this->assertSame(2, $userStory->versions()->count());
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
