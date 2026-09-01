<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
{
    /**
     * Keep validation errors off the other profile forms.
     */
    protected $errorBag = 'updateAiSettings';

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ai_provider' => ['required', 'string', Rule::in(array_keys(config('ai.providers')))],
            'ai_api_key' => ['required', 'string', 'max:255'],
        ];
    }
}
