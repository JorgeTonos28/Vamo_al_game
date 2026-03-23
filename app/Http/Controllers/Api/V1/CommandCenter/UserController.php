<?php

namespace App\Http\Controllers\Api\V1\CommandCenter;

use App\Actions\Admin\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CommandCenter\InviteUserRequest;
use App\Services\CommandCenter\CommandCenterUserDirectoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    ): JsonResponse {
        $user = $inviteUser->handle($request->user(), $request->validated());

        return ApiResponse::success(
            $request,
            [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'account_role' => $user->account_role?->value,
                    'account_role_label' => $user->account_role?->label(),
                    'league_memberships_count' => 0,
                    'has_completed_onboarding' => $user->hasCompletedOnboarding(),
                    'invited_at' => $user->invited_at?->toDateTimeString(),
                    'created_at' => $user->created_at?->toDateTimeString(),
                ],
            ],
            'Invitacion enviada correctamente.',
            201,
        );
    }
}
