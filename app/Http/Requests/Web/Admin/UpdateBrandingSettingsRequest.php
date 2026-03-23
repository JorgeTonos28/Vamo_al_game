<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGeneralAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'nullable',
                'file',
                'max:5120',
                'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml',
            ],
            'favicon' => [
                'nullable',
                'file',
                'max:2048',
                'mimetypes:image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon',
            ],
        ];
    }
}
