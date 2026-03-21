<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GoogleExchangeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'handoff' => ['required', 'string', 'size:64'],
        ];
    }

    public function handoff(): string
    {
        return $this->string('handoff')->value();
    }
}
