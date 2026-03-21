<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Actions\Auth\UpdateUserPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\PasswordUpdateRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PasswordController extends Controller
{
    public function __invoke(
        PasswordUpdateRequest $request,
        UpdateUserPassword $updateUserPassword,
    ): JsonResponse {
        $updateUserPassword->handle($request->user(), $request->string('password')->value());

        return ApiResponse::success($request, null, 'Contrasena actualizada.');
    }
}
