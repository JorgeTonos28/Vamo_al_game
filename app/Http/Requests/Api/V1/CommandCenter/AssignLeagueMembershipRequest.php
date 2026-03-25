<?php

namespace App\Http\Requests\Api\V1\CommandCenter;

use App\Enums\LeagueMembershipRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignLeagueMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-command-center') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'league_id' => [
                'required',
                'integer',
                Rule::exists('leagues', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'role' => [
                'required',
                Rule::in([
                    LeagueMembershipRole::Admin->value,
                    LeagueMembershipRole::Member->value,
                ]),
            ],
        ];
    }
}
