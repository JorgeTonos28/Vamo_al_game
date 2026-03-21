<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $this->string('code')->trim()->value() === ''
                && $this->string('recovery_code')->trim()->value() === ''
            ) {
                $validator->errors()->add(
                    'code',
                    'Debes proporcionar un codigo o un recovery code.',
                );
            }
        });
    }

    public function challengeToken(): string
    {
        return $this->string('challenge_token')->trim()->value();
    }

    public function code(): ?string
    {
        return $this->string('code')->trim()->value() ?: null;
    }

    public function recoveryCode(): ?string
    {
        return $this->string('recovery_code')->trim()->value() ?: null;
    }

    public function hasValidCode(User $user): bool
    {
        $code = $this->code();

        if (! $code || ! $user->two_factor_secret) {
            return false;
        }

        return app(TwoFactorAuthenticationProvider::class)->verify(
            Fortify::currentEncrypter()->decrypt($user->two_factor_secret),
            $code,
        );
    }

    public function validRecoveryCode(User $user): ?string
    {
        $recoveryCode = $this->recoveryCode();

        if (! $recoveryCode || ! $user->two_factor_recovery_codes) {
            return null;
        }

        return collect($user->recoveryCodes())->first(
            fn (string $code): bool => hash_equals($code, $recoveryCode),
        );
    }
}
