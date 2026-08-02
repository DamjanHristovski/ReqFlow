<?php

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Comment::class, $this->route('specification')]);
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => [
                'nullable',
                Rule::exists('comments', 'id')->where('specification_id', $this->route('specification')->id),
            ],
        ];
    }
}
