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

class GenerateAcceptanceCriteriaJob implements ShouldQueue
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

        $userStory = $this->aiRequest->userStory;

        $criteria = $ai->generateAcceptanceCriteria($this->aiRequest->user, $userStory);
        $created = $content->createAcceptanceCriteria($userStory, $criteria, $this->aiRequest->user);

        $this->aiRequest->update([
            'status' => AiRequest::STATUS_COMPLETED,
            'response' => $created === 0
                ? 'No new acceptance criteria to add — the story already looks covered.'
                : "Generated {$created} acceptance ".Str::plural('criterion', $created).'.',
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
