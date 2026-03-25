<?php

namespace App\Http\Requests\Web\Admin;

use App\Enums\AccountRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-command-center') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $role = $this->input('account_role');
        $leagueId = $this->input('league_id');
        $documentId = $this->input('document_id');
        $phone = $this->input('phone');
        $address = $this->input('address');

        $this->merge([
            'account_role' => $role !== '' ? $role : null,
            'league_id' => $leagueId !== '' ? $leagueId : null,
            'document_id' => $documentId !== '' ? $documentId : null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'document_id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'account_role' => ['nullable', Rule::enum(AccountRole::class)],
            'league_id' => [
                'nullable',
                'integer',
                Rule::exists('leagues', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
        ];
    }
}
