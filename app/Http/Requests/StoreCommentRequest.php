<?php

namespace App\Http\Requests;

use App\Models\AcceptanceCriterion;
use App\Models\Comment;
use App\Models\Specification;
use App\Models\UserStory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Comment::class, $this->commentable()]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where($this->commentableColumn(), $this->commentable()->id),
            ],
        ];
    }

    private function commentable(): Specification|UserStory|AcceptanceCriterion
    {
        return $this->route('specification') ?? $this->route('userStory') ?? $this->route('acceptanceCriterion');
    }

    private function commentableColumn(): string
    {
        return match (true) {
            $this->route('specification') !== null => 'specification_id',
            $this->route('userStory') !== null => 'user_story_id',
            default => 'acceptance_criterion_id',
        };
    }
}
