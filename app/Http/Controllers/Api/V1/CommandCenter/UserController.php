<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Actions\Admin\InviteUser;
use App\Enums\LeagueMembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommandCenter\AssignLeagueMembershipRequest;
use App\Http\Requests\Api\V1\CommandCenter\InviteUserRequest;
use App\Models\League;
use App\Models\User;
use App\Services\CommandCenter\CommandCenterUserDirectoryService;
use App\Services\LeagueMemberships\LeagueMembershipManager;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(
        Request $request,
        CommandCenterUserDirectoryService $directoryService,
    ): JsonResponse {
        return ApiResponse::success(
            $request,
            $directoryService->payload(),
            'Usuarios del centro de mando cargados.',
        );
    }

    public function store(
        InviteUserRequest $request,
        InviteUser $inviteUser,
        CommandCenterUserDirectoryService $directoryService,
    ): JsonResponse {
        $user = $inviteUser->handle($request->user(), $request->validated());

        return ApiResponse::success(
            $request,
            [
                'user' => $directoryService->userRow($user->fresh(['leagueMemberships.league'])),
            ],
            'Invitacion enviada correctamente.',
            201,
        );
    }

    public function assignLeague(
        AssignLeagueMembershipRequest $request,
        User $user,
        LeagueMembershipManager $membershipManager,
        CommandCenterUserDirectoryService $directoryService,
    ): JsonResponse {
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

        return ApiResponse::success(
            $request,
            [
                'user' => $directoryService->userRow($user->fresh(['leagueMemberships.league'])),
            ],
            'Liga asignada correctamente al usuario.',
        );
    }
}
