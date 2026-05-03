<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\ModularController;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use App\Modules\Purchase\Models\Purchase;
use App\Modules\Sales\Models\SalesInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('query'));

        if (empty($query)) {
            return response()->json(['data' => []]);
        }

        $results = [];

        $like = '%'.$query.'%';

        $products = Product::query()
            ->withSum(['batches as stock_on_hand' => fn ($builder) => $builder->where('is_active', true)], 'quantity_available')
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('product_code', 'like', $like)
                    ->orWhere('generic_name', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn (Product $product) => [
                'key' => "product-{$product->id}",
                'label' => $product->name,
                'description' => "SKU: {$product->sku} | Stock: ".number_format((float) ($product->stock_on_hand ?? 0), 3),
                'type' => 'Product',
                'route' => "/app/inventory/products?id={$product->id}",
            ]);
        $results = array_merge($results, $products->toArray());

        $sales = SalesInvoice::query()
            ->with('customer:id,name')
            ->where(function ($builder) use ($like) {
                $builder->where('invoice_no', 'like', $like)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $like));
            })
            ->latest('invoice_date')
            ->limit(5)
            ->get()
            ->map(fn (SalesInvoice $invoice) => [
                'key' => "sales-{$invoice->id}",
                'label' => $invoice->invoice_no,
                'description' => trim(($invoice->customer?->name ?: 'Walk-in customer').' | NPR '.number_format((float) $invoice->grand_total, 2)),
                'type' => 'Sales',
                'route' => "/app/sales/invoices?id={$invoice->id}",
            ]);
        $results = array_merge($results, $sales->toArray());

        $purchases = Purchase::query()
            ->with('supplier:id,name')
            ->where(function ($builder) use ($like) {
                $builder->where('purchase_no', 'like', $like)
                    ->orWhere('supplier_invoice_no', 'like', $like)
                    ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', $like));
            })
            ->latest('purchase_date')
            ->limit(5)
            ->get()
            ->map(fn (Purchase $purchase) => [
                'key' => "purchase-{$purchase->id}",
                'label' => $purchase->purchase_no,
                'description' => trim(($purchase->supplier?->name ?: 'Supplier').' | NPR '.number_format((float) $purchase->grand_total, 2)),
                'type' => 'Purchase',
                'route' => "/app/purchases/bills?id={$purchase->id}",
            ]);
        $results = array_merge($results, $purchases->toArray());

        $customers = Customer::query()
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('pan_number', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn (Customer $customer) => [
                'key' => "customer-{$customer->id}",
                'label' => $customer->name,
                'description' => "Phone: {$customer->phone}",
                'type' => 'Customer',
                'route' => "/app/party/customers?id={$customer->id}",
            ]);
        $results = array_merge($results, $customers->toArray());

        $suppliers = Supplier::query()
            ->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('pan_number', 'like', $like);
            })
            ->limit(5)
            ->get()
            ->map(fn (Supplier $supplier) => [
                'key' => "supplier-{$supplier->id}",
                'label' => $supplier->name,
                'description' => "Phone: {$supplier->phone}",
                'type' => 'Supplier',
                'route' => "/app/party/suppliers?id={$supplier->id}",
            ]);
        $results = array_merge($results, $suppliers->toArray());

        return response()->json(['data' => $results]);
    }
}
