<?php

namespace App\Core\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
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
}
