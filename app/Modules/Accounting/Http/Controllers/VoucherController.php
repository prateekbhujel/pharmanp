<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Accounting\Http\Requests\VoucherStoreRequest;
use App\Modules\Accounting\Http\Resources\VoucherResource;
use App\Modules\Accounting\Models\Voucher;
use App\Modules\Accounting\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="ACCOUNTING - Finance",
 *     description="API endpoints for ACCOUNTING - Finance"
 * )
 */
class VoucherController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/accounting/vouchers",
     *     summary="Api Vouchers Index",
     *     tags={"ACCOUNTING - Vouchers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, VoucherService $service): JsonResponse
    {
        $vouchers = $service->table(
            TableQueryData::fromRequest($request, ['voucher_type', 'from', 'to']),
            $request->user(),
        );

        return response()->json(VoucherResource::collection($vouchers)->response()->getData(true));
    }

    /**
     * @OA\Get(
     *     path="/accounting/vouchers/{voucher}",
     *     summary="Api Vouchers Show",
     *     tags={"ACCOUNTING - Vouchers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Voucher $voucher): VoucherResource
    {
        return new VoucherResource($voucher->load('entries'));
    }

    /**
     * @OA\Post(
     *     path="/accounting/vouchers",
     *     summary="Api Vouchers Store",
     *     tags={"ACCOUNTING - Vouchers"},
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
    public function store(VoucherStoreRequest $request, VoucherService $service): JsonResponse
    {
        $voucher = $service->create($request->validated(), $request->user());

        return (new VoucherResource($voucher))
            ->additional(['message' => 'Voucher posted.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/accounting/vouchers/{voucher}",
     *     summary="Api Vouchers Update",
     *     tags={"ACCOUNTING - Vouchers"},
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
    public function update(VoucherStoreRequest $request, Voucher $voucher, VoucherService $service): VoucherResource
    {
        return (new VoucherResource($service->update($voucher, $request->validated(), $request->user())))
            ->additional(['message' => 'Voucher updated.']);
    }

    /**
     * @OA\Delete(
     *     path="/accounting/vouchers/{voucher}",
     *     summary="Api Vouchers Destroy",
     *     tags={"ACCOUNTING - Vouchers"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Voucher $voucher, VoucherService $service): JsonResponse
    {
        $service->delete($voucher);

        return response()->json(['message' => 'Voucher deleted successfully.']);
    }
}
