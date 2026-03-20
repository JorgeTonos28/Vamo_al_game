<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use JsonSerializable;

class ApiResponse
{
    public static function success(
        Request $request,
        mixed $data = null,
        string $message = 'Solicitud completada.',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => self::resolveData($data, $request),
            'errors' => [],
            'meta' => $meta,
        ], $status);
    }

    public static function error(
        string $message,
        int $status,
        array $errors = [],
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
            'meta' => $meta,
        ], $status);
    }

    public static function paginated(
        Request $request,
        LengthAwarePaginator $paginator,
        callable $transform,
        string $message = 'Solicitud completada.',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return self::success(
            $request,
            array_map($transform, $paginator->items()),
            $message,
            $status,
            array_merge($meta, [
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ]),
        );
    }

    private static function resolveData(mixed $data, Request $request): mixed
    {
        if ($data instanceof JsonResource || $data instanceof AnonymousResourceCollection) {
            return $data->resolve($request);
        }

        if ($data instanceof Arrayable) {
            return $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        return $data;
    }
}
