<?php

namespace App\Http\Requests;

use App\Models\Specification;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Specification::class, $this->route('project')]);
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
