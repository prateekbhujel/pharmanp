<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\Product;
use App\Modules\Party\Models\Customer;
use App\Modules\Party\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->query('query');
        
        if (empty($query)) {
            return response()->json(['data' => []]);
        }

        $results = [];

        // Search Products
        $products = Product::where('name', 'like', "%{$query}%")
            ->orWhere('sku', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn($p) => [
                'key' => "product-{$p->id}",
                'label' => $p->name,
                'description' => "SKU: {$p->sku} | Stock: {$p->stock_on_hand}",
                'type' => 'Product',
                'route' => "/app/inventory/products?id={$p->id}"
            ]);
        $results = array_merge($results, $products->toArray());

        // Search Customers
        $customers = Customer::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn($c) => [
                'key' => "customer-{$c->id}",
                'label' => $c->name,
                'description' => "Phone: {$c->phone}",
                'type' => 'Customer',
                'route' => "/app/party/customers?id={$c->id}"
            ]);
        $results = array_merge($results, $customers->toArray());

        // Search Suppliers
        $suppliers = Supplier::where('name', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'key' => "supplier-{$s->id}",
                'label' => $s->name,
                'description' => "Phone: {$s->phone}",
                'type' => 'Supplier',
                'route' => "/app/party/suppliers?id={$s->id}"
            ]);
        $results = array_merge($results, $suppliers->toArray());

        return response()->json(['data' => $results]);
    }
}
