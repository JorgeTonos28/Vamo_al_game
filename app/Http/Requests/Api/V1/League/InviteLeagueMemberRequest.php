<?php

namespace App\Http\Requests\Api\V1\League;

use App\Enums\AccountRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteLeagueMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $documentId = $this->input('document_id');
        $phone = $this->input('phone');
        $address = $this->input('address');

        $this->merge([
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
            'account_role' => [
                'required',
                Rule::in([
                    AccountRole::LeagueAdmin->value,
                    AccountRole::Member->value,
                ]),
            ],
        ];
    }
}
