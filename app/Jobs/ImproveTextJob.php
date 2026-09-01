<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\AiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ImproveTextJob implements ShouldQueue
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

    public function handle(AiService $ai): void
    {
        $this->aiRequest->update(['status' => AiRequest::STATUS_PROCESSING]);

        $response = $ai->improveText(
            $this->aiRequest->user,
            Str::headline($this->aiRequest->field),
            $this->aiRequest->prompt
        );

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
