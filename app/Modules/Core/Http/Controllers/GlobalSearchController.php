<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Core\DTOs\GlobalSearchData;
use App\Modules\Core\Http\Requests\GlobalSearchRequest;
use App\Modules\Core\Http\Resources\GlobalSearchResultResource;
use App\Modules\Core\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="CORE - Platform",
 *     description="API endpoints for CORE - Platform"
 * )
 */
class GlobalSearchController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/search",
     *     summary="Api Search",
     *     tags={"CORE - Global Search"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $service): JsonResponse
    {
        return $this->resource(
            GlobalSearchResultResource::collection($service->search(GlobalSearchData::fromRequest($request), $request->user())),
            'Search results retrieved successfully.',
        );
    }
}
