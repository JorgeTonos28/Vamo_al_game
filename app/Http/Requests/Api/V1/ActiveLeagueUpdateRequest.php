<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ActiveLeagueUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()?->isGeneralAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
        ];
    }
}
