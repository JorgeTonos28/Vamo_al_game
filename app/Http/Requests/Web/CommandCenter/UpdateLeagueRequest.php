<?php

namespace App\Http\Requests\Web\CommandCenter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeagueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-command-center') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('name')) {
            $payload['name'] = preg_replace('/\s+/', ' ', trim((string) $this->input('name')));
        }

        if ($this->has('emoji')) {
            $payload['emoji'] = filled($this->input('emoji'))
                ? trim((string) $this->input('emoji'))
                : null;
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'emoji' => ['sometimes', 'nullable', 'string', 'max:16'],
        ];
    }
}
