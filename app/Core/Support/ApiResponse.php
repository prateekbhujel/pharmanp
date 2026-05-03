<?php

namespace App\Core\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = Response::HTTP_OK): JsonResponse
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->additional([
                'status' => 'success',
                'message' => $message,
            ])->response()->setStatusCode($status);
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
