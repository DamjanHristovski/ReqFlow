<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    use HasFactory;

    public const TYPE_IMPROVE_TEXT = 'improve_text';

    public const TYPE_GENERATE_NEXT_STEPS = 'generate_next_steps';

    public const TYPE_IMPORT_PDF = 'import_pdf';

    public const TYPE_GENERATE_USER_STORIES = 'generate_user_stories';

    public const TYPE_GENERATE_ACCEPTANCE_CRITERIA = 'generate_acceptance_criteria';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'project_id',
        'specification_id',
        'user_story_id',
        'acceptance_criterion_id',
        'type',
        'field',
        'status',
        'prompt',
        'response',
        'error_message',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }

    public function userStory(): BelongsTo
    {
        return $this->belongsTo(UserStory::class);
    }

    public function acceptanceCriterion(): BelongsTo
    {
        return $this->belongsTo(AcceptanceCriterion::class);
    }

    public function subject(): Specification|UserStory|AcceptanceCriterion|null
    {
        return $this->specification ?? $this->userStory ?? $this->acceptanceCriterion;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
