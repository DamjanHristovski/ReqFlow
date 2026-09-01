<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\AiService;
use App\Services\GeneratedContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/**
 * Generates user stories (each with acceptance criteria) from an existing
 * specification's content. The PDF-driven variant lives in ImportPdfJob.
 */
class GenerateUserStoriesJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly AiRequest $aiRequest,
    ) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(AiService $ai, GeneratedContentService $content): void
    {
        $this->aiRequest->update(['status' => AiRequest::STATUS_PROCESSING]);

        $specification = $this->aiRequest->specification;
        $user = $this->aiRequest->user;

        $existing = $specification->project->userStories
            ->map(fn ($story) => trim($story->title.' — '.$story->description))
            ->all();

        $stories = $ai->generateUserStories($user, $ai->summarizeSpecification($specification), existing: $existing);
        $created = $content->createUserStories($specification->project, $stories, $user);

        $this->aiRequest->update([
            'status' => AiRequest::STATUS_COMPLETED,
            'response' => $created === 0
                ? 'No new user stories to add — the spec already looks covered.'
                : "Generated {$created} user ".Str::plural('story', $created).'.',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->aiRequest->update([
            'status' => AiRequest::STATUS_FAILED,
            'error_message' => $exception?->getMessage(),
        ]);
    }
}
