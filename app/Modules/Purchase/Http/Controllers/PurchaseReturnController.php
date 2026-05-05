<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Modules\Purchase\Http\Requests\PurchaseReturnRequest;
use App\Modules\Purchase\Http\Resources\PurchaseReturnResource;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Services\PurchaseReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * @OA\Tag(
 *     name="PURCHASE - Purchase Workflow",
 *     description="API endpoints for PURCHASE - Purchase Workflow"
 * )
 */
class PurchaseReturnController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/purchase/returns",
     *     summary="Api Returns Index",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request, PurchaseReturnService $service): JsonResponse
    {
        $query = $service->table(
            TableQueryData::fromRequest($request, ['deleted', 'supplier_id', 'return_type', 'return_mode', 'from', 'to']),
            $request->user(),
        );

        return PurchaseReturnResource::collection($query)->response();
    }

    /**
     * @OA\Get(
     *     path="/purchase/returns/{purchaseReturn}",
     *     summary="Api Returns Show",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Request $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service): PurchaseReturnResource
    {
        $service->assertAccessible($purchaseReturn, $request->user());

        return new PurchaseReturnResource($purchaseReturn->load(['supplier', 'purchase', 'items.product', 'items.batch']));
    }

    /**
     * @OA\Post(
     *     path="/purchase/returns",
     *     summary="Api Returns Store",
     *     tags={"PURCHASE - Returns"},
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
    public function store(PurchaseReturnRequest $request, PurchaseReturnService $service): JsonResponse
    {
        $purchaseReturn = $service->save($request->validated(), $request->user());

        return (new PurchaseReturnResource($purchaseReturn))
            ->additional([
                'message' => 'Purchase return posted.',
                'print_url' => route('purchase-returns.print', $purchaseReturn),
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Put(
     *     path="/purchase/returns/{purchaseReturn}",
     *     summary="Api Returns Update",
     *     tags={"PURCHASE - Returns"},
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
    public function update(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service): PurchaseReturnResource
    {
        $service->assertAccessible($purchaseReturn, $request->user());

        return new PurchaseReturnResource($service->save($request->validated(), $request->user(), $purchaseReturn));
    }

    /**
     * @OA\Delete(
     *     path="/purchase/returns/{purchaseReturn}",
     *     summary="Api Returns Destroy",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function destroy(Request $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service): JsonResponse
    {
        $service->delete($purchaseReturn, $request->user());

        return response()->json(['message' => 'Purchase return deleted and stock restored.']);
    }

    /**
     * @OA\Get(
     *     path="/purchase/returns/purchases",
     *     summary="Api Purchase Returns Purchases",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function purchases(Request $request, PurchaseReturnService $service): JsonResponse
    {
        return response()->json(['data' => $service->purchaseOptions($request->user(), $request->integer('supplier_id'))]);
    }

    /**
     * @OA\Get(
     *     path="/purchase/returns/purchases/{purchase}/items",
     *     summary="Api Purchase Returns Purchase Items",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function items(Request $request, Purchase $purchase, PurchaseReturnService $service): JsonResponse
    {
        return response()->json(['data' => $service->purchaseItems($purchase, $request->user())]);
    }

    /**
     * @OA\Get(
     *     path="/purchase/returns/batches",
     *     summary="Api Purchase Returns Batches",
     *     tags={"PURCHASE - Returns"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function supplierBatches(Request $request, PurchaseReturnService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->batchOptions(
                $request->integer('product_id'),
                $request->integer('supplier_id'),
                null,
                $request->user(),
            ),
        ]);
    }

    public function print(Request $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service): View
    {
        $service->assertAccessible($purchaseReturn, $request->user());

        return view('prints.purchase-return', $service->printPayload($purchaseReturn));
    }

    public function pdf(Request $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service)
    {
        $service->assertAccessible($purchaseReturn, $request->user());

        return Pdf::loadView('prints.purchase-return', $service->printPayload($purchaseReturn))
            ->setPaper('a4', 'portrait')
            ->stream($purchaseReturn->return_no.'.pdf');
    }
}
