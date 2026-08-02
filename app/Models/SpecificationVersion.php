<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecificationVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'specification_id',
        'version_number',
        'content',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function specification(): BelongsTo
    {
        return $this->belongsTo(Specification::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
