<?php

namespace App\Http\Requests;

use App\Models\UserStory;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserStoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [UserStory::class, $this->route('project')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
