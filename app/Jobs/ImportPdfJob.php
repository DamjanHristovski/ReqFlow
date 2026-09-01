<?php

namespace App\Jobs;

use App\Models\AiRequest;
use App\Services\AiService;
use App\Services\GeneratedContentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Project-level "Import from PDF". The uploaded PDF path is carried on the
 * AiRequest's `prompt` column (relative to the 'local' disk).
 *
 * Two-step triage, per the design: one structured call extracts a specification
 * and flags which artifacts the document contains; each true flag drives a
 * follow-up generation. Created records are ordinary editable drafts.
 */
class ImportPdfJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    private const DISK = 'local';

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

        $user = $this->aiRequest->user;
        $project = $this->aiRequest->project;
        $path = $this->aiRequest->prompt;

        $extraction = $ai->extractSpecificationFromPdf($user, $path, self::DISK);

        $summary = [];

        if (($extraction['has_specification'] ?? false) && ! empty($extraction['specification'])) {
            $content->createSpecification($project, $extraction['specification'], $user);
            $summary[] = '1 specification';
        }

        if ($extraction['has_user_stories'] ?? false) {
            $existing = $project->userStories()->get()
                ->map(fn ($story) => trim($story->title.' — '.$story->description))
                ->all();

            $stories = $ai->generateUserStories($user, null, $path, self::DISK, existing: $existing);
            $count = $content->createUserStories($project, $stories, $user);

            if ($count > 0) {
                $summary[] = "{$count} user ".Str::plural('story', $count);
            }
        }

        $this->cleanUp($path);

        $this->aiRequest->update([
            'status' => AiRequest::STATUS_COMPLETED,
            'response' => $summary === []
                ? 'No specification or user stories were found in the document.'
                : 'Created '.implode(' and ', $summary).'.',
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->cleanUp($this->aiRequest->prompt);

        $this->aiRequest->update([
            'status' => AiRequest::STATUS_FAILED,
            'error_message' => $exception?->getMessage(),
        ]);
    }

    private function cleanUp(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
