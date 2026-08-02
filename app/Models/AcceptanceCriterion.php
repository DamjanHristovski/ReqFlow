<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcceptanceCriterion extends Model
{
    use HasFactory;

    public const STATUS_MET = 'met';

    public const STATUS_NOT_MET = 'not_met';

    public const STATUSES = [
        self::STATUS_MET,
        self::STATUS_NOT_MET,
    ];

    public const STATUS_LABELS = [
        self::STATUS_MET => 'Met',
        self::STATUS_NOT_MET => 'Not Met',
    ];

    /**
     * Long-text content fields eligible for AI "Improve".
     */
    public const IMPROVABLE_FIELDS = [
        'description',
    ];

    protected $fillable = [
        'user_story_id',
        'description',
        'status',
        'created_by',
    ];

    public function userStory(): BelongsTo
    {
        return $this->belongsTo(UserStory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }
}
