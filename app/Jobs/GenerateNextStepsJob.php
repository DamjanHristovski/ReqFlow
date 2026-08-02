<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\OpenAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateNextStepsJob implements ShouldQueue
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

    public function handle(OpenAIService $openAI): void
    {
        $this->aiRequest->update(['status' => AiRequest::STATUS_PROCESSING]);

        $response = $openAI->generateNextSteps($this->aiRequest->subject());

        $this->aiRequest->update([
            'status' => AiRequest::STATUS_COMPLETED,
            'response' => $response,
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
