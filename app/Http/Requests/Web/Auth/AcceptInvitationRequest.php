<?php

namespace App\Http\Requests\Web\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcceptInvitationRequest extends FormRequest
{
    use PasswordValidationRules;

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
        $userId = $this->route('invitation')->user_id;

        return [
            'token' => ['required', 'string'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'document_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'document_id')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.confirmed' => 'La confirmacion de la contrasena no coincide.',
        ];
    }
}
