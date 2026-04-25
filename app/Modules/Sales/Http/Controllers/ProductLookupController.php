<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Resources\ProductResource;
use App\Modules\Inventory\Models\Product;
use Illuminate\Http\Request;

class ProductLookupController extends Controller
{
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
                'category:id,name',
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
