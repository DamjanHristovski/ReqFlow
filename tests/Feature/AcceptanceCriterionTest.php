<?php

namespace Tests\Feature;

use App\Models\AcceptanceCriterion;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcceptanceCriterionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();

        $this->get(route('user-stories.acceptance-criteria.create', $userStory))->assertRedirect(route('login'));
    }

    public function test_member_can_create_an_acceptance_criterion(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('user-stories.acceptance-criteria.store', $userStory), [
            'description' => 'User sees a confirmation message.',
            'status' => AcceptanceCriterion::STATUS_NOT_MET,
        ]);

        $acceptanceCriterion = AcceptanceCriterion::firstWhere('description', 'User sees a confirmation message.');

        $response->assertRedirect(route('acceptance-criteria.show', $acceptanceCriterion));
        $this->assertNotNull($acceptanceCriterion);
        $this->assertSame($userStory->id, $acceptanceCriterion->user_story_id);
    }

    public function test_outsider_cannot_create_an_acceptance_criterion(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('user-stories.acceptance-criteria.store', $userStory), [
                'description' => 'Something',
                'status' => AcceptanceCriterion::STATUS_NOT_MET,
            ])
            ->assertForbidden();
    }

    public function test_acceptance_criterion_creation_requires_a_description(): void
    {
        [, $owner, , $userStory] = $this->createUserStoryWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('user-stories.acceptance-criteria.store', $userStory), [
                'status' => AcceptanceCriterion::STATUS_NOT_MET,
            ])
            ->assertSessionHasErrors('description');
    }

    public function test_acceptance_criterion_creation_rejects_an_invalid_status(): void
    {
        [, $owner, , $userStory] = $this->createUserStoryWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('user-stories.acceptance-criteria.store', $userStory), [
                'description' => 'Something',
                'status' => 'archived',
            ])
            ->assertSessionHasErrors('status');
    }

    public function test_member_can_view_an_acceptance_criterion(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);

        $this->actingAs($member)->get(route('acceptance-criteria.show', $acceptanceCriterion))->assertOk();
    }

    public function test_outsider_cannot_view_an_acceptance_criterion(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->get(route('acceptance-criteria.show', $acceptanceCriterion))->assertForbidden();
    }

    public function test_member_can_update_an_acceptance_criterion(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);

        $this->actingAs($member)
            ->patch(route('acceptance-criteria.update', $acceptanceCriterion), [
                'description' => 'Updated criterion',
                'status' => AcceptanceCriterion::STATUS_MET,
            ])
            ->assertRedirect(route('acceptance-criteria.show', $acceptanceCriterion));

        $acceptanceCriterion->refresh();
        $this->assertSame('Updated criterion', $acceptanceCriterion->description);
        $this->assertSame(AcceptanceCriterion::STATUS_MET, $acceptanceCriterion->status);
    }

    public function test_outsider_cannot_update_an_acceptance_criterion(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->patch(route('acceptance-criteria.update', $acceptanceCriterion), [
                'description' => 'Updated criterion',
                'status' => AcceptanceCriterion::STATUS_MET,
            ])
            ->assertForbidden();
    }

    public function test_member_can_delete_an_acceptance_criterion(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);

        $this->actingAs($member)
            ->delete(route('acceptance-criteria.destroy', $acceptanceCriterion))
            ->assertRedirect(route('user-stories.show', $userStory));

        $this->assertDatabaseMissing('acceptance_criteria', ['id' => $acceptanceCriterion->id]);
    }

    public function test_outsider_cannot_delete_an_acceptance_criterion(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->delete(route('acceptance-criteria.destroy', $acceptanceCriterion))->assertForbidden();
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: UserStory}
     */
    private function createUserStoryWithOwnerAndMember(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['created_by' => $owner->id]);
        $team->teamMembers()->create(['user_id' => $owner->id, 'role' => TeamMember::ROLE_OWNER]);

        $member = User::factory()->create();
        $team->teamMembers()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER]);

        $project = Project::factory()->create(['team_id' => $team->id]);
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        return [$team, $owner, $member, $userStory];
    }
}
