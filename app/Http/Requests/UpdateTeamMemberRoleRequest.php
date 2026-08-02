<?php

namespace App\Http\Requests;

use App\Models\TeamMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateMemberRole', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in([TeamMember::ROLE_OWNER, TeamMember::ROLE_MEMBER])],
        ];
    }
}
