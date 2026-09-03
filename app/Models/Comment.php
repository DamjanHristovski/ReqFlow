<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'specification_id',
        'user_story_id',
        'acceptance_criterion_id',
        'user_id',
        'parent_id',
        'body',
    ];

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

    public function commentable(): Specification|UserStory|AcceptanceCriterion
    {
        return $this->specification ?? $this->userStory ?? $this->acceptanceCriterion;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * Build an in-memory reply tree from a flat collection of comments —
     * one query, no N+1 regardless of nesting depth. Top-level comments come
     * back newest-first; replies within each thread are chronological.
     */
    public static function buildTree(Collection $comments): Collection
    {
        $sorted = $comments->sortBy('created_at')->values();
        $byParent = $sorted->groupBy('parent_id');

        $sorted->each(fn (self $comment) => $comment->setRelation(
            'replies', $byParent->get($comment->id, collect())->values()
        ));

        return $byParent->get(null, collect())->sortByDesc('created_at')->values();
    }

    /**
     * Count of all descendants (children, grandchildren, ...), not just
     * direct replies. Assumes the 'replies' relation is already populated
     * in memory (via buildTree) — does not run additional queries.
     */
    public function totalReplyCount(): int
    {
        return $this->replies->sum(fn (self $reply) => 1 + $reply->totalReplyCount());
    }
}
