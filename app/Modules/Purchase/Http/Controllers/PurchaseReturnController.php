<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Core\DTOs\TableQueryData;
use App\Http\Controllers\ModularController;
use App\Models\Setting;
use App\Modules\Inventory\Models\Batch;
use App\Modules\Purchase\Http\Requests\PurchaseReturnRequest;
use App\Modules\Purchase\Http\Resources\PurchaseReturnResource;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Purchase\Models\PurchaseItem;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\PurchaseReturnItem;
use App\Modules\Purchase\Services\PurchaseReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
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
    public function show(PurchaseReturn $purchaseReturn): PurchaseReturnResource
    {
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
    public function destroy(PurchaseReturn $purchaseReturn, PurchaseReturnService $service): JsonResponse
    {
        $service->delete($purchaseReturn, request()->user());

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
    public function purchases(): JsonResponse
    {
        $purchases = Purchase::query()
            ->when(request()->user()?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('tenant_id', $tenantId))
            ->where('supplier_id', request()->integer('supplier_id'))
            ->latest('purchase_date')
            ->limit(100)
            ->get(['id', 'purchase_no', 'supplier_invoice_no', 'purchase_date', 'grand_total']);

        return response()->json([
            'data' => $purchases->map(fn (Purchase $purchase) => [
                'id' => $purchase->id,
                'label' => trim($purchase->purchase_no.' | '.($purchase->supplier_invoice_no ?: 'No supplier bill').' | '.$purchase->purchase_date?->toDateString().' | Rs. '.number_format((float) $purchase->grand_total, 2)),
            ])->values(),
        ]);
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
    public function items(Purchase $purchase): JsonResponse
    {
        $items = PurchaseItem::query()
            ->with(['product:id,name', 'batch:id,batch_no,expires_at,quantity_available,purchase_price,mrp'])
            ->where('purchase_id', $purchase->id)
            ->get()
            ->map(function (PurchaseItem $item) use ($purchase) {
                $alreadyReturned = (float) PurchaseReturnItem::query()
                    ->where('purchase_item_id', $item->id)
                    ->sum('return_qty');
                $originalQty = (float) $item->quantity + (float) $item->free_quantity;
                $maxReturnable = max(0, $originalQty - $alreadyReturned);
                $returnQty = $maxReturnable > 0 ? 1 : 0;
                $rate = (float) $item->purchase_price;
                $netRate = round($rate - ($rate * (float) $item->discount_percent / 100), 2);

                return [
                    'purchase_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'batch_id' => $item->batch_id,
                    'batch_no' => $item->batch?->batch_no,
                    'original_qty' => $originalQty,
                    'already_returned' => $alreadyReturned,
                    'max_returnable' => $maxReturnable,
                    'return_qty' => $returnQty,
                    'rate' => $rate,
                    'discount_percent' => (float) $item->discount_percent,
                    'discount_amount' => round($returnQty * max(0, $rate - $netRate), 2),
                    'net_rate' => $netRate,
                    'return_amount' => round($returnQty * $netRate, 2),
                    'batch_options' => $this->batchOptions($item->product_id, $purchase->supplier_id, $item->batch_id),
                ];
            })
            ->values();

        return response()->json(['data' => $items]);
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
    public function supplierBatches(): JsonResponse
    {
        return response()->json([
            'data' => $this->batchOptions(
                request()->integer('product_id'),
                request()->integer('supplier_id'),
                null,
            ),
        ]);
    }

    public function print(PurchaseReturn $purchaseReturn): View
    {
        return view('prints.purchase-return', $this->printData($purchaseReturn));
    }

    public function pdf(PurchaseReturn $purchaseReturn)
    {
        return Pdf::loadView('prints.purchase-return', $this->printData($purchaseReturn))
            ->setPaper('a4', 'portrait')
            ->stream($purchaseReturn->return_no.'.pdf');
    }

    private function batchOptions(?int $productId, ?int $supplierId, ?int $selectedBatchId): array
    {
        return Batch::query()
            ->where(function (Builder $query) use ($productId, $supplierId, $selectedBatchId) {
                $query->where(function (Builder $available) use ($productId, $supplierId) {
                    $available->where('is_active', true)
                        ->where('quantity_available', '>', 0)
                        ->when($productId, fn (Builder $builder) => $builder->where('product_id', $productId))
                        ->when($supplierId, fn (Builder $builder) => $builder->where('supplier_id', $supplierId));
                });

                if ($selectedBatchId) {
                    $query->orWhere('id', $selectedBatchId);
                }
            })
            ->orderBy('expires_at')
            ->orderBy('batch_no')
            ->limit(100)
            ->get()
            ->map(fn (Batch $batch) => [
                'id' => $batch->id,
                'label' => trim($batch->batch_no.' | Exp: '.($batch->expires_at?->toDateString() ?: '-').' | Qty: '.number_format((float) $batch->quantity_available, 3)),
                'product_id' => $batch->product_id,
                'batch_no' => $batch->batch_no,
                'expires_at' => $batch->expires_at?->toDateString(),
                'quantity_available' => (float) $batch->quantity_available,
                'purchase_price' => (float) $batch->purchase_price,
                'mrp' => (float) $batch->mrp,
            ])
            ->values()
            ->all();
    }

    private function printData(PurchaseReturn $purchaseReturn): array
    {
        return [
            'purchaseReturn' => $purchaseReturn->load(['supplier', 'purchase', 'items.product', 'items.batch']),
            'branding' => Setting::getValue('app.branding', ['app_name' => 'PharmaNP']),
        ];
    }
}
