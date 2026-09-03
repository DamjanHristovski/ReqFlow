<?php

namespace App\Http\Requests;

use App\Models\AcceptanceCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcceptanceCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [AcceptanceCriterion::class, $this->route('userStory')]);
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(AcceptanceCriterion::STATUSES)],
        ];
    }
}
