<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        return ApiResponse::success(
            $request,
            new UserResource($user),
            'Usuario cargado.',
        );
    }
}
