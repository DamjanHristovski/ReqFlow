<?php

namespace Tests\Feature;

use App\Models\AcceptanceCriterion;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Specification;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->post(route('comments.store', $specification), ['body' => 'Hello'])
            ->assertRedirect(route('login'));
    }

    public function test_member_can_add_a_comment(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('comments.store', $specification), [
            'body' => 'This looks good.',
        ]);

        $response->assertRedirect(route('specifications.show', $specification));
        $this->assertSame(1, $specification->comments()->count());
        $this->assertSame($member->id, $specification->comments()->first()->user_id);
    }

    public function test_owner_can_add_a_comment(): void
    {
        [, $owner, , $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->actingAs($owner)
            ->post(route('comments.store', $specification), ['body' => 'Nice work.'])
            ->assertRedirect();

        $this->assertSame(1, $specification->comments()->count());
    }

    public function test_outsider_cannot_add_a_comment(): void
    {
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('comments.store', $specification), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, $specification->comments()->count());
    }

    public function test_comment_requires_a_body(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->actingAs($member)
            ->post(route('comments.store', $specification), [])
            ->assertSessionHasErrors('body');
    }

    public function test_author_can_delete_their_own_comment(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $comment = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id]);

        $this->actingAs($member)
            ->delete(route('comments.destroy', $comment))
            ->assertRedirect(route('specifications.show', $specification));

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_owner_cannot_delete_another_members_comment(): void
    {
        [, $owner, $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $comment = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id]);

        $this->actingAs($owner)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }

    public function test_member_cannot_delete_another_members_comment(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $otherMember = User::factory()->create();
        $comment = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $otherMember->id]);

        $this->actingAs($member)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();
    }

    public function test_outsider_cannot_delete_a_comment(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $comment = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->delete(route('comments.destroy', $comment))
            ->assertForbidden();
    }

    public function test_member_can_reply_to_a_comment(): void
    {
        [, $owner, $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $comment = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $owner->id]);

        $this->actingAs($member)->post(route('comments.store', $specification), [
            'body' => 'A reply.',
            'parent_id' => $comment->id,
        ])->assertRedirect();

        $reply = Comment::firstWhere('body', 'A reply.');
        $this->assertNotNull($reply);
        $this->assertSame($comment->id, $reply->parent_id);
    }

    public function test_reply_cannot_reference_a_comment_from_another_specification(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $otherSpecification = Specification::factory()->create(['project_id' => $specification->project_id]);
        $foreignComment = Comment::factory()->create(['specification_id' => $otherSpecification->id]);

        $this->actingAs($member)
            ->post(route('comments.store', $specification), [
                'body' => 'Sneaky reply',
                'parent_id' => $foreignComment->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_total_reply_count_includes_all_descendants(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $root = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id]);
        $child = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'parent_id' => $root->id]);
        Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'parent_id' => $child->id]);
        Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'parent_id' => $root->id]);

        $tree = Comment::buildTree($specification->comments()->get());
        $rootInTree = $tree->firstWhere('id', $root->id);

        $this->assertSame(3, $rootInTree->totalReplyCount());
    }

    public function test_deleting_a_comment_cascades_to_its_replies(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $root = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id]);
        $reply = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'parent_id' => $root->id]);

        $this->actingAs($member)->delete(route('comments.destroy', $root))->assertRedirect();

        $this->assertDatabaseMissing('comments', ['id' => $root->id]);
        $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
    }

    public function test_specification_show_page_renders_nested_replies(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $root = Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'body' => 'Root comment']);
        Comment::factory()->create(['specification_id' => $specification->id, 'user_id' => $member->id, 'parent_id' => $root->id, 'body' => 'Nested reply']);

        $response = $this->actingAs($member)->get(route('specifications.show', $specification));

        $response->assertOk();
        $response->assertSee('Root comment');
        $response->assertSee('Nested reply');
        $response->assertSee('View replies (1)');
    }

    public function test_member_can_add_a_comment_to_a_user_story(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('user-stories.comments.store', $userStory), [
            'body' => 'This looks good.',
        ]);

        $response->assertRedirect(route('user-stories.show', $userStory));
        $this->assertSame(1, $userStory->comments()->count());
        $this->assertSame($member->id, $userStory->comments()->first()->user_id);
    }

    public function test_outsider_cannot_add_a_comment_to_a_user_story(): void
    {
        [, , , $userStory] = $this->createUserStoryWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('user-stories.comments.store', $userStory), ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_member_can_add_a_comment_to_an_acceptance_criterion(): void
    {
        [, , $member, $acceptanceCriterion] = $this->createAcceptanceCriterionWithOwnerAndMember();

        $response = $this->actingAs($member)->post(route('acceptance-criteria.comments.store', $acceptanceCriterion), [
            'body' => 'This looks good.',
        ]);

        $response->assertRedirect(route('acceptance-criteria.show', $acceptanceCriterion));
        $this->assertSame(1, $acceptanceCriterion->comments()->count());
        $this->assertSame($member->id, $acceptanceCriterion->comments()->first()->user_id);
    }

    public function test_outsider_cannot_add_a_comment_to_an_acceptance_criterion(): void
    {
        [, , , $acceptanceCriterion] = $this->createAcceptanceCriterionWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('acceptance-criteria.comments.store', $acceptanceCriterion), ['body' => 'Hello'])
            ->assertForbidden();
    }

    public function test_reply_cannot_reference_a_comment_from_another_user_story(): void
    {
        [, , $member, $userStory] = $this->createUserStoryWithOwnerAndMember();
        $otherUserStory = UserStory::factory()->create(['project_id' => $userStory->project_id]);
        $foreignComment = Comment::factory()->create(['specification_id' => null, 'user_story_id' => $otherUserStory->id]);

        $this->actingAs($member)
            ->post(route('user-stories.comments.store', $userStory), [
                'body' => 'Sneaky reply',
                'parent_id' => $foreignComment->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Specification}
     */
    private function createSpecificationWithOwnerAndMember(): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['created_by' => $owner->id]);
        $team->teamMembers()->create(['user_id' => $owner->id, 'role' => TeamMember::ROLE_OWNER]);

        $member = User::factory()->create();
        $team->teamMembers()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER]);

        $project = Project::factory()->create(['team_id' => $team->id]);
        $specification = Specification::factory()->create(['project_id' => $project->id]);

        return [$team, $owner, $member, $specification];
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

    /**
     * @return array{0: Team, 1: User, 2: User, 3: AcceptanceCriterion}
     */
    private function createAcceptanceCriterionWithOwnerAndMember(): array
    {
        [$team, $owner, $member, $userStory] = $this->createUserStoryWithOwnerAndMember();
        $acceptanceCriterion = AcceptanceCriterion::factory()->create(['user_story_id' => $userStory->id]);

        return [$team, $owner, $member, $acceptanceCriterion];
    }
}
