<?php

namespace App\Http\Requests\Web\League;

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
        $email = $this->input('email');
        $jerseyNumber = $this->input('jersey_number');

        $this->merge([
            'document_id' => $documentId !== '' ? $documentId : null,
            'phone' => $phone !== '' ? $phone : null,
            'address' => $address !== '' ? $address : null,
            'email' => $email !== '' ? $email : null,
            'jersey_number' => $jerseyNumber !== '' ? $jerseyNumber : null,
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
            'document_id' => ['required', 'string', 'max:50', Rule::unique('users', 'document_id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'jersey_number' => ['nullable', 'integer', 'min:0', 'max:99'],
            'account_role' => [
                'required',
                Rule::in([
                    AccountRole::LeagueAdmin->value,
                    AccountRole::Member->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Debes indicar el nombre.',
            'last_name.required' => 'Debes indicar el apellido.',
            'document_id.required' => 'Debes indicar la cédula.',
            'document_id.unique' => 'Ya existe un usuario con esa cédula.',
            'email.email' => 'El correo debe ser válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'jersey_number.integer' => 'La chaqueta debe ser un número entero.',
            'jersey_number.min' => 'La chaqueta no puede ser menor que 0.',
            'jersey_number.max' => 'La chaqueta no puede ser mayor que 99.',
            'account_role.required' => 'Debes seleccionar el rol de la cuenta.',
            'account_role.in' => 'Selecciona un rol válido para la cuenta.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'document_id' => 'cédula',
            'phone' => 'teléfono',
            'address' => 'dirección',
            'email' => 'correo',
            'jersey_number' => 'chaqueta',
            'account_role' => 'rol',
        ];
    }
}
