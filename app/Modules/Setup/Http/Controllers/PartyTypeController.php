<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Setup\DTOs\SetupTypeData;
use App\Modules\Setup\Http\Requests\PartyTypeRequest;
use App\Modules\Setup\Http\Resources\SetupTypeResource;
use App\Modules\Setup\Models\PartyType;
use App\Modules\Setup\Services\SetupTypeService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class PartyTypeController extends ModularController
{
    public function __construct(private readonly SetupTypeService $types) {}

    /**
     * @OA\Get(
     *     path="/settings/party-types",
     *     summary="Api Settings Party Types Index",
     *     tags={"SETUP - Party Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(): JsonResponse
    {
        return $this->resource(SetupTypeResource::collection($this->types->all(PartyType::class)), 'Party types retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/settings/party-types",
     *     summary="Api Settings Party Types Store",
     *     tags={"SETUP - Party Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/PartyTypeRequest")),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(PartyTypeRequest $request): JsonResponse
    {
        return $this->resource(
            new SetupTypeResource($this->types->create(PartyType::class, SetupTypeData::fromRequest($request))),
            'Party type created.',
            201,
        );
    }

    /**
     * @OA\Put(
     *     path="/settings/party-types/{partyType}",
     *     summary="Api Settings Party Types Update",
     *     tags={"SETUP - Party Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(ref="#/components/schemas/PartyTypeRequest")),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(PartyTypeRequest $request, PartyType $partyType): JsonResponse
    {
        return $this->resource(new SetupTypeResource($this->types->update($partyType, SetupTypeData::fromRequest($request))), 'Party type updated.');
    }

    /**
     * @OA\Delete(
     *     path="/settings/party-types/{partyType}",
     *     summary="Api Settings Party Types Destroy",
     *     tags={"SETUP - Party Types"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function destroy(PartyType $partyType): JsonResponse
    {
        $this->types->delete($partyType);

        return $this->success(null, 'Party type deleted.');
    }
}
