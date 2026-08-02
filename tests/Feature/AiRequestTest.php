<?php

namespace Tests\Feature;

use App\Jobs\GenerateNextStepsJob;
use App\Jobs\ImproveSpecificationTextJob;
use App\Models\AiRequest;
use App\Models\Project;
use App\Models\Specification;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\OpenAIService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class AiRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->post(route('ai.improve-text', $specification), ['field' => 'description'])
            ->assertRedirect(route('login'));
    }

    public function test_member_can_request_improve_text(): void
    {
        Queue::fake();
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $specification->update(['description' => 'Users need login']);

        $this->actingAs($member)
            ->post(route('ai.improve-text', $specification), ['field' => 'description'])
            ->assertRedirect(route('specifications.edit', $specification));

        $this->assertDatabaseHas('ai_requests', [
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => 'description',
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'Users need login',
        ]);

        Queue::assertPushed(ImproveSpecificationTextJob::class);
    }

    public function test_improve_text_rejects_an_invalid_field(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->actingAs($member)
            ->post(route('ai.improve-text', $specification), ['field' => 'title'])
            ->assertSessionHasErrors('field');
    }

    public function test_improve_text_does_nothing_for_an_empty_field(): void
    {
        Queue::fake();
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $specification->update(['description' => null]);

        $this->actingAs($member)
            ->post(route('ai.improve-text', $specification), ['field' => 'description'])
            ->assertRedirect();

        $this->assertDatabaseCount('ai_requests', 0);
        Queue::assertNotPushed(ImproveSpecificationTextJob::class);
    }

    public function test_outsider_cannot_request_improve_text(): void
    {
        Queue::fake();
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();
        $specification->update(['description' => 'Users need login']);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('ai.improve-text', $specification), ['field' => 'description'])
            ->assertForbidden();

        Queue::assertNotPushed(ImproveSpecificationTextJob::class);
    }

    public function test_member_can_request_next_steps(): void
    {
        Queue::fake();
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();

        $this->actingAs($member)
            ->post(route('ai.generate-next-steps', $specification))
            ->assertRedirect(route('specifications.show', $specification));

        $this->assertDatabaseHas('ai_requests', [
            'specification_id' => $specification->id,
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        Queue::assertPushed(GenerateNextStepsJob::class);
    }

    public function test_outsider_cannot_request_next_steps(): void
    {
        Queue::fake();
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('ai.generate-next-steps', $specification))
            ->assertForbidden();

        Queue::assertNotPushed(GenerateNextStepsJob::class);
    }

    public function test_improve_text_job_completes_the_request(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [['message' => ['content' => 'Users should be able to authenticate securely.']]],
            ]),
        ]);

        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => 'description',
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'Users need login',
        ]);

        (new ImproveSpecificationTextJob($aiRequest))->handle(app(OpenAIService::class));

        $aiRequest->refresh();
        $this->assertTrue($aiRequest->isCompleted());
        $this->assertSame('Users should be able to authenticate securely.', $aiRequest->response);
    }

    public function test_generate_next_steps_job_completes_the_request(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [['message' => ['content' => 'Missing Information: none. Next Actions: ship it.']]],
            ]),
        ]);

        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'field' => null,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        (new GenerateNextStepsJob($aiRequest))->handle(app(OpenAIService::class));

        $aiRequest->refresh();
        $this->assertTrue($aiRequest->isCompleted());
        $this->assertStringContainsString('Next Actions', $aiRequest->response);
    }

    public function test_job_failure_marks_the_request_failed(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        $job = new ImproveSpecificationTextJob($aiRequest);
        $job->failed(new Exception('OpenAI API unreachable'));

        $aiRequest->refresh();
        $this->assertTrue($aiRequest->isFailed());
        $this->assertSame('OpenAI API unreachable', $aiRequest->error_message);
    }

    public function test_member_can_apply_a_completed_improve_text_suggestion(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $specification->update(['description' => 'Users need login']);
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => 'description',
            'status' => AiRequest::STATUS_COMPLETED,
            'prompt' => 'Users need login',
            'response' => 'Users should be able to authenticate securely.',
        ]);

        $this->actingAs($member)
            ->post(route('ai-requests.apply', $aiRequest))
            ->assertRedirect(route('specifications.edit', $specification));

        $this->assertSame('Users should be able to authenticate securely.', $specification->fresh()->description);
    }

    public function test_cannot_apply_a_pending_ai_request(): void
    {
        [, , $member, $specification] = $this->createSpecificationWithOwnerAndMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        $this->actingAs($member)
            ->post(route('ai-requests.apply', $aiRequest))
            ->assertNotFound();
    }

    public function test_outsider_cannot_apply_a_suggestion(): void
    {
        [, , , $specification] = $this->createSpecificationWithOwnerAndMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'type' => AiRequest::TYPE_IMPROVE_TEXT,
            'field' => 'description',
            'status' => AiRequest::STATUS_COMPLETED,
            'response' => 'Improved text',
        ]);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->post(route('ai-requests.apply', $aiRequest))
            ->assertForbidden();
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
}
