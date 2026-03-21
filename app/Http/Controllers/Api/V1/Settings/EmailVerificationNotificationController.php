<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return ApiResponse::success(
                $request,
                new UserResource($request->user()),
                'El correo ya se encuentra verificado.',
            );
        }

        $request->user()->sendEmailVerificationNotification();

        return ApiResponse::success(
            $request,
            new UserResource($request->user()),
            'Se envio un nuevo enlace de verificacion.',
        );
    }
}
