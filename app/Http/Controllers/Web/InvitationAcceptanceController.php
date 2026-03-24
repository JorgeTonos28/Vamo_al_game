<?php

namespace App\Http\Controllers\Web;

use App\Actions\Auth\AcceptInvitation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Auth\AcceptInvitationRequest;
use App\Models\UserInvitation;
use App\Services\Invitations\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(
        Request $request,
        UserInvitation $invitation,
        UserInvitationService $userInvitationService,
    ): Response {
        abort_unless(
            $userInvitationService->isValid($invitation, (string) $request->query('token')),
            404,
        );

        $user = $invitation->user;

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'id' => $invitation->id,
                'token' => (string) $request->query('token'),
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'document_id' => $user->document_id,
                'phone' => $user->phone,
                'address' => $user->address,
                'account_role' => $user->account_role?->value,
                'account_role_label' => $user->account_role?->label(),
            ],
        ]);
    }

    public function store(
        AcceptInvitationRequest $request,
        UserInvitation $invitation,
        AcceptInvitation $acceptInvitation,
    ): RedirectResponse {
        $user = $acceptInvitation->handle(
            $invitation,
            $request->string('token')->value(),
            $request->validated(),
        );

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(
            $user->isGeneralAdmin()
                ? route('command-center.dashboard')
                : route('dashboard'),
        );
    }
}
