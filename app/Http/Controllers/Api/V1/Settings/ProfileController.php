<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Auth\DeleteUserAccount;
use App\Actions\Auth\UpdateUserProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\ProfileDeleteRequest;
use App\Http\Requests\Api\V1\Settings\ProfileUpdateRequest;
use App\Http\Resources\V1\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(
        ProfileUpdateRequest $request,
        UpdateUserProfile $updateUserProfile,
    ): JsonResponse {
        $user = $updateUserProfile->handle($request->user(), $request->validated());

        return ApiResponse::success(
            $request,
            new UserResource($user),
            'Perfil actualizado.',
        );
    }

    public function destroy(
        ProfileDeleteRequest $request,
        DeleteUserAccount $deleteUserAccount,
    ): JsonResponse {
        $deleteUserAccount->handle($request->user());

        return ApiResponse::success($request, null, 'Cuenta eliminada.');
    }
}
