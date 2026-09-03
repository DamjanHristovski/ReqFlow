<?php

namespace App\Http\Requests;

use App\Models\AcceptanceCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAcceptanceCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('acceptanceCriterion'));
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string'],
            'status' => ['required', 'string', Rule::in(AcceptanceCriterion::STATUSES)],
        ];
    }
}
