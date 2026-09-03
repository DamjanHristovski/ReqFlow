<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserStory extends Model
{
    use HasFactory, SoftDeletes;

    public const VERSIONED_FIELDS = [
        'title',
        'description',
    ];

    /**
     * Long-text content fields eligible for AI "Improve" — deliberately
     * excludes title, which is a short identifying label rather than prose.
     */
    public const IMPROVABLE_FIELDS = [
        'description',
    ];

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'current_version',
        'created_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SpecificationVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }

    public function acceptanceCriteria(): HasMany
    {
        return $this->hasMany(AcceptanceCriterion::class);
    }
}
