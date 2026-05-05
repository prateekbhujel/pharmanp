<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Models\Product;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="SALES - POS and Invoices",
 *     description="API endpoints for SALES - POS and Invoices"
 * )
 */
class ProductLookupController extends ModularController
{
    /**
     * @OA\Get(
     *     path="/sales/product-lookup",
     *     summary="Api Sales Product Lookup",
     *     tags={"SALES - Product Lookup"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="Successful response"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'barcode' => ['nullable', 'string', 'max:120'],
        ]);

        $query = Product::query()
            ->with([
                'company:id,name',
                'unit:id,name',
                'division:id,name,code',
                'batches' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('quantity_available', '>', 0)
                    ->orderByRaw('expires_at IS NULL')
                    ->orderBy('expires_at')
                    ->limit(8),
            ])
            ->withSum(['batches as stock_on_hand' => fn ($query) => $query->where('is_active', true)], 'quantity_available')
            ->where('is_active', true);

        if (! empty($validated['barcode'])) {
            $query->where(function ($builder) use ($validated) {
                $builder->where('barcode', $validated['barcode'])
                    ->orWhere('sku', $validated['barcode']);
            });
        } elseif (! empty($validated['q'])) {
            $search = $validated['q'];
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('generic_name', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            });
        }

        return ProductResource::collection($query->orderBy('name')->limit(20)->get());
    }
}
