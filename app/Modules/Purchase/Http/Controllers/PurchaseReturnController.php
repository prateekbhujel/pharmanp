<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
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
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function index(): JsonResponse
    {
        $sorts = [
            'return_no' => 'return_no',
            'return_date' => 'return_date',
            'grand_total' => 'grand_total',
            'created_at' => 'created_at',
        ];
        $sortField = $sorts[request('sort_field', 'return_date')] ?? 'return_date';
        $sortOrder = request('sort_order') === 'asc' ? 'asc' : 'desc';
        $search = trim((string) request('search'));

        $query = PurchaseReturn::query()
            ->with(['supplier:id,name', 'purchase:id,purchase_no,supplier_invoice_no'])
            ->withCount('items')
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('return_no', 'like', '%'.$search.'%')
                        ->orWhere('notes', 'like', '%'.$search.'%')
                        ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('purchase', fn (Builder $purchase) => $purchase
                            ->where('purchase_no', 'like', '%'.$search.'%')
                            ->orWhere('supplier_invoice_no', 'like', '%'.$search.'%'));
                });
            })
            ->when(request()->filled('supplier_id'), fn (Builder $builder) => $builder->where('supplier_id', request()->integer('supplier_id')))
            ->when(request('return_mode') === 'bill', fn (Builder $builder) => $builder->whereNotNull('purchase_id'))
            ->when(in_array(request('return_mode'), ['manual', 'product'], true), fn (Builder $builder) => $builder->whereNull('purchase_id'))
            ->when(request()->filled('from'), fn (Builder $builder) => $builder->whereDate('return_date', '>=', request('from')))
            ->when(request()->filled('to'), fn (Builder $builder) => $builder->whereDate('return_date', '<=', request('to')))
            ->orderBy($sortField, $sortOrder)
            ->orderByDesc('id')
            ->paginate(min(100, max(5, request()->integer('per_page', 15))));

        return PurchaseReturnResource::collection($query)->response();
    }

    public function show(PurchaseReturn $purchaseReturn): PurchaseReturnResource
    {
        return new PurchaseReturnResource($purchaseReturn->load(['supplier', 'purchase', 'items.product', 'items.batch']));
    }

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

    public function update(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn, PurchaseReturnService $service): PurchaseReturnResource
    {
        return new PurchaseReturnResource($service->save($request->validated(), $request->user(), $purchaseReturn));
    }

    public function destroy(PurchaseReturn $purchaseReturn, PurchaseReturnService $service): JsonResponse
    {
        $service->delete($purchaseReturn, request()->user());

        return response()->json(['message' => 'Purchase return deleted and stock restored.']);
    }

    public function purchases(): JsonResponse
    {
        $purchases = Purchase::query()
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
