<?php

namespace App\Http\Controllers\Web\CommandCenter;

use App\Actions\Admin\InviteUser;
use App\Enums\LeagueMembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\InviteUserRequest;
use App\Http\Requests\Web\CommandCenter\AssignLeagueMembershipRequest;
use App\Models\League;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterUserDirectoryService;
use App\Services\LeagueMemberships\LeagueMembershipManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(CommandCenterUserDirectoryService $directoryService): Response
    {
        $payload = $directoryService->payload();

        return Inertia::render('command-center/Users', [
            'roleOptions' => $payload['role_options'],
            'leagueRoleOptions' => $payload['league_role_options'],
            'leagueOptions' => $payload['league_options'],
            'users' => $payload['users'],
        ]);
    }

    public function store(InviteUserRequest $request, InviteUser $inviteUser): RedirectResponse
    {
        $inviteUser->handle($request->user(), $request->validated());

        return to_route('command-center.users.index')
            ->with('status', 'Invitacion enviada correctamente.');
    }

    public function assignLeague(
        AssignLeagueMembershipRequest $request,
        User $user,
        LeagueMembershipManager $membershipManager,
    ): RedirectResponse {
        if ($user->isGeneralAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'Los administradores generales no se asignan a ligas operativas.',
            ]);
        }

        /** @var League $league */
        $league = League::query()->findOrFail($request->integer('league_id'));

        $membershipManager->assign(
            $user,
            $league,
            LeagueMembershipRole::from($request->string('role')->value()),
            $request->user(),
        );

        return to_route('command-center.users.index')
            ->with('status', 'Liga asignada correctamente al usuario.');
    }
}
