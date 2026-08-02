<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specification extends Model
{
    use HasFactory, SoftDeletes;

    public const VERSIONED_FIELDS = [
        'title',
        'description',
        'goals',
        'scope',
        'functional_requirements',
        'non_functional_requirements',
    ];

    protected $fillable = [
        'project_id',
        'title',
        'description',
        'goals',
        'scope',
        'functional_requirements',
        'non_functional_requirements',
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
}
