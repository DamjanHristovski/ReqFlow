<?php

namespace Tests\Feature;

use App\Jobs\GenerateAcceptanceCriteriaJob;
use App\Jobs\GenerateUserStoriesJob;
use App\Jobs\ImportPdfJob;
use App\Models\AiRequest;
use App\Models\Project;
use App\Models\Specification;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserStory;
use App\Services\AiService;
use App\Services\GeneratedContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiFeaturesTest extends TestCase
{
    use RefreshDatabase;

    // ---- Profile AI settings ------------------------------------------------

    public function test_user_can_save_ai_settings_and_key_is_encrypted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('ai-settings.update'), ['ai_provider' => 'openai', 'ai_api_key' => 'sk-secret-1234skyw'])
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('openai', $user->ai_provider);
        $this->assertSame('sk-secret-1234skyw', $user->ai_api_key);
        $this->assertTrue($user->hasAiConfigured());
        $this->assertSame('••••skyw', $user->maskedAiKey());

        $raw = DB::table('users')->where('id', $user->id)->value('ai_api_key');
        $this->assertNotSame('sk-secret-1234skyw', $raw);
    }

    public function test_ai_settings_provider_must_be_supported(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('ai-settings.update'), ['ai_provider' => 'skynet', 'ai_api_key' => 'sk-x'])
            ->assertSessionHasErrorsIn('updateAiSettings', ['ai_provider']);
    }

    public function test_user_can_remove_ai_settings(): void
    {
        $user = User::factory()->create(['ai_provider' => 'gemini', 'ai_api_key' => 'g-key-1234']);

        $this->actingAs($user)
            ->delete(route('ai-settings.destroy'))
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertNull($user->ai_provider);
        $this->assertNull($user->ai_api_key);
        $this->assertFalse($user->hasAiConfigured());
    }

    // ---- No-key gating ------------------------------------------------------

    public function test_ai_action_is_blocked_without_a_key(): void
    {
        Queue::fake();
        [, , $member, $specification] = $this->specWithMember(withKey: false);
        $specification->update(['description' => 'Users need login']);

        $this->actingAs($member)
            ->post(route('ai.improve-text', $specification), ['field' => 'description'])
            ->assertRedirect();

        $this->assertDatabaseCount('ai_requests', 0);
        Queue::assertNothingPushed();
    }

    // ---- Generate user stories ---------------------------------------------

    public function test_member_can_request_user_story_generation(): void
    {
        Queue::fake();
        [, , $member, $specification] = $this->specWithMember();

        $this->actingAs($member)
            ->post(route('ai.generate-user-stories', $specification))
            ->assertRedirect(route('specifications.show', $specification));

        $this->assertDatabaseHas('ai_requests', [
            'specification_id' => $specification->id,
            'type' => AiRequest::TYPE_GENERATE_USER_STORIES,
            'status' => AiRequest::STATUS_PENDING,
        ]);
        Queue::assertPushed(GenerateUserStoriesJob::class);
    }

    public function test_generate_user_stories_job_creates_stories_and_criteria(): void
    {
        [, , $member, $specification] = $this->specWithMember();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('summarizeSpecification')->andReturn('summary');
            $mock->shouldReceive('generateUserStories')->andReturn([
                ['title' => 'Log in', 'description' => 'As a user, I want to log in.', 'acceptance_criteria' => ['a', 'b', 'c']],
                ['title' => 'Log out', 'description' => 'As a user, I want to log out.', 'acceptance_criteria' => ['x', 'y']],
            ]);
        });

        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_USER_STORIES,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        (new GenerateUserStoriesJob($aiRequest))->handle(app(AiService::class), app(GeneratedContentService::class));

        $this->assertSame(2, $specification->project->userStories()->count());
        $story = $specification->project->userStories()->where('title', 'Log in')->first();
        $this->assertSame(3, $story->acceptanceCriteria()->count());
        $this->assertTrue($aiRequest->fresh()->isCompleted());
    }

    public function test_generate_user_stories_job_reports_when_nothing_new(): void
    {
        [, , $member, $specification] = $this->specWithMember();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('summarizeSpecification')->andReturn('summary');
            $mock->shouldReceive('generateUserStories')->andReturn([]); // model found nothing new
        });

        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_USER_STORIES,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        (new GenerateUserStoriesJob($aiRequest))->handle(app(AiService::class), app(GeneratedContentService::class));

        $this->assertSame(0, $specification->project->userStories()->count());
        $this->assertTrue($aiRequest->fresh()->isCompleted());
        $this->assertStringContainsString('No new user stories', $aiRequest->fresh()->response);
    }

    // ---- Generate acceptance criteria --------------------------------------

    public function test_generate_acceptance_criteria_job_creates_criteria(): void
    {
        [, , $member, $project] = $this->projectWithMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateAcceptanceCriteria')->andReturn(['first', 'second', 'third']);
        });

        $aiRequest = AiRequest::factory()->create([
            'specification_id' => null,
            'user_story_id' => $userStory->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_ACCEPTANCE_CRITERIA,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        (new GenerateAcceptanceCriteriaJob($aiRequest))->handle(app(AiService::class), app(GeneratedContentService::class));

        $this->assertSame(3, $userStory->acceptanceCriteria()->count());
        $this->assertTrue($aiRequest->fresh()->isCompleted());
    }

    public function test_generate_acceptance_criteria_job_reports_when_nothing_new(): void
    {
        [, , $member, $project] = $this->projectWithMember();
        $userStory = UserStory::factory()->create(['project_id' => $project->id]);

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('generateAcceptanceCriteria')->andReturn([]); // nothing new
        });

        $aiRequest = AiRequest::factory()->create([
            'specification_id' => null,
            'user_story_id' => $userStory->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_ACCEPTANCE_CRITERIA,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        (new GenerateAcceptanceCriteriaJob($aiRequest))->handle(app(AiService::class), app(GeneratedContentService::class));

        $this->assertSame(0, $userStory->acceptanceCriteria()->count());
        $this->assertStringContainsString('No new acceptance criteria', $aiRequest->fresh()->response);
    }

    // ---- Status polling -----------------------------------------------------

    public function test_member_can_poll_ai_request_status(): void
    {
        [, , $member, $specification] = $this->specWithMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'status' => AiRequest::STATUS_COMPLETED,
        ]);

        $this->actingAs($member)
            ->getJson(route('ai-requests.status', $aiRequest))
            ->assertOk()
            ->assertJson(['status' => 'completed', 'done' => true]);
    }

    public function test_member_can_poll_a_project_scoped_import_status(): void
    {
        [, , $member, $project] = $this->projectWithMember();
        $aiRequest = $project->aiRequests()->create([
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_IMPORT_PDF,
            'status' => AiRequest::STATUS_PROCESSING,
            'prompt' => 'ai-imports/x.pdf',
        ]);

        $this->actingAs($member)
            ->getJson(route('ai-requests.status', $aiRequest))
            ->assertOk()
            ->assertJson(['status' => 'processing', 'done' => false]);
    }

    public function test_outsider_cannot_poll_ai_request_status(): void
    {
        [, , , $specification] = $this->specWithMember();
        $aiRequest = AiRequest::factory()->create([
            'specification_id' => $specification->id,
            'type' => AiRequest::TYPE_GENERATE_NEXT_STEPS,
            'status' => AiRequest::STATUS_PENDING,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('ai-requests.status', $aiRequest))
            ->assertForbidden();
    }

    // ---- PDF import ---------------------------------------------------------

    public function test_import_pdf_queues_a_job(): void
    {
        Queue::fake();
        Storage::fake('local');
        [, , $member, $project] = $this->projectWithMember();

        $this->actingAs($member)
            ->post(route('ai.import-pdf', $project), [
                'document' => UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('ai_requests', [
            'project_id' => $project->id,
            'type' => AiRequest::TYPE_IMPORT_PDF,
            'status' => AiRequest::STATUS_PENDING,
        ]);
        Queue::assertPushed(ImportPdfJob::class);
    }

    public function test_import_pdf_rejects_non_pdf(): void
    {
        Queue::fake();
        Storage::fake('local');
        [, , $member, $project] = $this->projectWithMember();

        $this->actingAs($member)
            ->post(route('ai.import-pdf', $project), [
                'document' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('document');

        Queue::assertNothingPushed();
    }

    public function test_import_pdf_job_creates_specification_and_stories(): void
    {
        Storage::fake('local');
        [, , $member, $project] = $this->projectWithMember();

        $this->mock(AiService::class, function ($mock) {
            $mock->shouldReceive('extractSpecificationFromPdf')->andReturn([
                'has_specification' => true,
                'has_user_stories' => true,
                'specification' => [
                    'title' => 'Imported Spec',
                    'description' => 'A description.',
                    'goals' => 'Goals.',
                    'scope' => 'Scope.',
                    'functional_requirements' => ['FR1', 'FR2'],
                    'non_functional_requirements' => ['NFR1'],
                ],
            ]);
            $mock->shouldReceive('generateUserStories')->andReturn([
                ['title' => 'Story A', 'description' => 'As a user...', 'acceptance_criteria' => ['ac1', 'ac2']],
            ]);
        });

        $aiRequest = $project->aiRequests()->create([
            'user_id' => $member->id,
            'type' => AiRequest::TYPE_IMPORT_PDF,
            'status' => AiRequest::STATUS_PENDING,
            'prompt' => 'ai-imports/whatever.pdf',
        ]);

        (new ImportPdfJob($aiRequest))->handle(app(AiService::class), app(GeneratedContentService::class));

        $spec = $project->specifications()->first();
        $this->assertNotNull($spec);
        $this->assertSame('Imported Spec', $spec->title);
        $this->assertStringContainsString('- FR1', $spec->functional_requirements);
        $this->assertSame(1, $project->userStories()->count());
        $this->assertTrue($aiRequest->fresh()->isCompleted());
    }

    // ---- helpers ------------------------------------------------------------

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Project}
     */
    private function projectWithMember(bool $withKey = true): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['created_by' => $owner->id]);
        $team->teamMembers()->create(['user_id' => $owner->id, 'role' => TeamMember::ROLE_OWNER]);

        $member = User::factory()->create($withKey ? ['ai_provider' => 'openai', 'ai_api_key' => 'sk-test-key'] : []);
        $team->teamMembers()->create(['user_id' => $member->id, 'role' => TeamMember::ROLE_MEMBER]);

        $project = Project::factory()->create(['team_id' => $team->id]);

        return [$team, $owner, $member, $project];
    }

    /**
     * @return array{0: Team, 1: User, 2: User, 3: Specification}
     */
    private function specWithMember(bool $withKey = true): array
    {
        [$team, $owner, $member, $project] = $this->projectWithMember($withKey);
        $specification = Specification::factory()->create(['project_id' => $project->id]);

        return [$team, $owner, $member, $specification];
    }
}
