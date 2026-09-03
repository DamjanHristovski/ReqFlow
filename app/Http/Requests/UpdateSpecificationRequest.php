<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('specification'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'goals' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'functional_requirements' => ['nullable', 'string'],
            'non_functional_requirements' => ['nullable', 'string'],
        ];
    }
}
