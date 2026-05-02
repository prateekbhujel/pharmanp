<?php

namespace App\Modules\Inventory\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository implements ProductRepositoryInterface
{
    private const SORTS = [
        'name' => 'products.name',
        'sku' => 'products.sku',
        'barcode' => 'products.barcode',
        'mrp' => 'products.mrp',
        'reorder_level' => 'products.reorder_level',
        'stock_on_hand' => 'stock_on_hand',
        'created_at' => 'products.created_at',
        'updated_at' => 'products.updated_at',
    ];

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $query = Product::query()
            ->select('products.*')
            ->when($user?->tenant_id, fn (Builder $builder, int $tenantId) => $builder->where('products.tenant_id', $tenantId))
            ->when((bool) ($table->filters['deleted'] ?? false), fn (Builder $builder) => $builder->onlyTrashed())
            ->with(['company:id,name', 'unit:id,name', 'category:id,name', 'division:id,name'])
            ->withSum(['batches as stock_on_hand' => fn ($query) => $query->where('is_active', true)], 'quantity_available');

        $this->applyFilters($query, $table);
        $sortColumn = self::SORTS[$table->sortField] ?? self::SORTS['updated_at'];

        if ($sortColumn === 'stock_on_hand') {
            $query->orderByRaw('COALESCE(stock_on_hand, 0) '.$table->sortOrder);
        } else {
            $query->orderBy($sortColumn, $table->sortOrder);
        }

        return $query->paginate($table->perPage, ['*'], 'page', $table->page);
    }

    public function create(array $payload): Product
    {
        return Product::query()->create($payload);
    }

    public function update(Product $product, array $payload): Product
    {
        $product->update($payload);

        return $product;
    }

    public function findTrashed(int $id): Product
    {
        return Product::query()->onlyTrashed()->findOrFail($id);
    }

    public function skuExists(?int $companyId, string $sku): bool
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('sku', $sku)
            ->exists();
    }

    private function applyFilters(Builder $query, TableQueryData $table): void
    {
        $query
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('products.name', 'like', '%'.$search.'%')
                        ->orWhere('products.product_code', 'like', '%'.$search.'%')
                        ->orWhere('products.hs_code', 'like', '%'.$search.'%')
                        ->orWhere('products.generic_name', 'like', '%'.$search.'%')
                        ->orWhere('products.sku', 'like', '%'.$search.'%')
                        ->orWhere('products.barcode', 'like', '%'.$search.'%')
                        ->orWhere('products.composition', 'like', '%'.$search.'%')
                        ->orWhere('products.group_name', 'like', '%'.$search.'%')
                        ->orWhere('products.manufacturer_name', 'like', '%'.$search.'%')
                        ->orWhere('products.packaging_type', 'like', '%'.$search.'%')
                        ->orWhere('products.case_movement', 'like', '%'.$search.'%');
                });
            })
            ->when(isset($table->filters['company_id']), fn (Builder $builder) => $builder->where('products.company_id', $table->filters['company_id']))
            ->when(isset($table->filters['category_id']), fn (Builder $builder) => $builder->where('products.category_id', $table->filters['category_id']))
            ->when(isset($table->filters['division_id']), fn (Builder $builder) => $builder->where('products.division_id', $table->filters['division_id']))
            ->when(isset($table->filters['is_active']), fn (Builder $builder) => $builder->where('products.is_active', (bool) $table->filters['is_active']));
    }
}
