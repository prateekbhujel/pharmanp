<?php

namespace App\Modules\Setup\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Setup\Http\Requests\FiscalYearIndexRequest;
use App\Modules\Setup\Http\Requests\FiscalYearRequest;
use App\Modules\Setup\Http\Resources\FiscalYearResource;
use App\Modules\Setup\Models\FiscalYear;
use App\Modules\Setup\Services\FiscalYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SETUP - Administration",
 *     description="API endpoints for SETUP - Administration"
 * )
 */
class FiscalYearController extends ModularController
{
    public function __construct(private readonly FiscalYearService $fiscalYears) {}

    /**
     * @OA\Get(
     *     path="/settings/fiscal-years",
     *     summary="Api Fiscal Years Index",
     *     tags={"SETUP - Fiscal Years"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(FiscalYearIndexRequest $request): JsonResponse
    {
        return $this->resource(FiscalYearResource::collection(
            $this->fiscalYears->table(TableQueryData::fromRequest($request), $request->user())
        ), 'Fiscal years retrieved successfully.');
    }

    /**
     * @OA\Post(
     *     path="/settings/fiscal-years",
     *     summary="Api Fiscal Years Store",
     *     tags={"SETUP - Fiscal Years"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(FiscalYearRequest $request): JsonResponse
    {
        $fiscalYear = $this->fiscalYears->save(new FiscalYear, $request->validated(), $request->user());

        return $this->resource(new FiscalYearResource($fiscalYear), 'Fiscal year created.', 201);
    }

    /**
     * @OA\Put(
     *     path="/settings/fiscal-years/{fiscal_year}",
     *     summary="Api Fiscal Years Update",
     *     tags={"SETUP - Fiscal Years"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\RequestBody(required=false, @OA\JsonContent(type="object", additionalProperties=true)),
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(FiscalYearRequest $request, FiscalYear $fiscalYear): FiscalYearResource
    {
        $this->fiscalYears->ensureOwnedRecord($fiscalYear, $request->user());

        $fiscalYear = $this->fiscalYears->save($fiscalYear, $request->validated(), $request->user());

        return new FiscalYearResource($fiscalYear);
    }

    /**
     * @OA\Delete(
     *     path="/settings/fiscal-years/{fiscal_year}",
     *     summary="Api Fiscal Years Destroy",
     *     tags={"SETUP - Fiscal Years"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, FiscalYear $fiscalYear): JsonResponse
    {
        abort_unless($request->user()?->is_owner || $request->user()?->can('settings.manage'), 403);
        $this->fiscalYears->delete($fiscalYear, $request->user());

        return $this->success(null, 'Fiscal year deleted.');
    }
}
