<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Invitations\UserInvitationService;
use App\Support\UserName;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CompleteGoogleInvitation
{
    public function __construct(
        private readonly UserInvitationService $userInvitationService,
    ) {}

    public function handle(UserInvitation $invitation, string $token, User $user): User
    {
        if (! $this->userInvitationService->isValid($invitation, $token)) {
            throw new HttpException(403, 'La invitacion no es valida o ya expiro.');
        }

        if (! hash_equals(strtolower($invitation->user->email), strtolower($user->email))) {
            throw new HttpException(403, 'La cuenta de Google no coincide con el correo invitado.');
        }

        $currentDisplayName = UserName::displayName($user->first_name, $user->last_name);
        $parsedName = $currentDisplayName !== 'Usuario'
            ? ['first_name' => $user->first_name, 'last_name' => $user->last_name]
            : UserName::fromFullName($user->name);

        $user->forceFill([
            'first_name' => $parsedName['first_name'] ?: $invitation->user->first_name,
            'last_name' => $parsedName['last_name'] ?: $invitation->user->last_name,
            'name' => UserName::displayName(
                $parsedName['first_name'] ?: $invitation->user->first_name,
                $parsedName['last_name'] ?: $invitation->user->last_name,
            ),
            'document_id' => blank($user->document_id ?: $invitation->user->document_id) ? null : ($user->document_id ?: $invitation->user->document_id),
            'phone' => blank($user->phone ?: $invitation->user->phone) ? null : ($user->phone ?: $invitation->user->phone),
            'address' => blank($user->address ?: $invitation->user->address) ? null : ($user->address ?: $invitation->user->address),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'onboarded_at' => now(),
        ])->save();

        $this->userInvitationService->accept($invitation);

        return $user->fresh();
    }
}
