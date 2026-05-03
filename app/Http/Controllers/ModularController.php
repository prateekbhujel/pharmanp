<?php

namespace App\Http\Controllers;

use App\Core\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

abstract class ModularController extends Controller
{
    protected function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return ApiResponse::success($data, $message, $status);
    }

    protected function error(
        string $message,
        int $status = Response::HTTP_BAD_REQUEST,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    protected function resource(
        JsonResource|ResourceCollection $resource,
        string $message = 'OK',
        int $status = Response::HTTP_OK
    ): JsonResponse {
        return $this->success($resource, $message, $status);
    }
}
