<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Invitations\UserInvitationService;
use App\Support\UserName;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    /**
     * @param  array{
     *     first_name: string,
     *     last_name: string,
     *     document_id?: string|null,
     *     phone?: string|null,
     *     address?: string|null,
     *     password: string,
     *     password_confirmation: string
     * }  $validated
     */
    public function handle(UserInvitation $invitation, string $token, array $validated): User
    {
        if (! $this->userInvitationService->isValid($invitation, $token)) {
            throw ValidationException::withMessages([
                'token' => 'La invitacion no es valida o ya expiro.',
            ]);
        }

        $user = $invitation->user;

        $user->forceFill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => UserName::displayName(
                $validated['first_name'],
                $validated['last_name'],
            ),
            'document_id' => blank($validated['document_id'] ?? null) ? null : $validated['document_id'],
            'phone' => blank($validated['phone'] ?? null) ? null : $validated['phone'],
            'address' => blank($validated['address'] ?? null) ? null : $validated['address'],
            'password' => $validated['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
            'onboarded_at' => now(),
        ])->save();

        $this->userInvitationService->accept($invitation);

        return $user->fresh();
    }
}
