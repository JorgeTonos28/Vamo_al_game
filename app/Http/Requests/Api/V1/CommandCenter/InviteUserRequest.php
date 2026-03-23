<?php

namespace App\Http\Requests\Api\V1\CommandCenter;

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

        $this->merge([
            'account_role' => $role !== '' ? $role : null,
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
        ];
    }
}
