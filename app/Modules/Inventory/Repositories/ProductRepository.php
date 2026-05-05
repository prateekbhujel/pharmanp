<?php

namespace App\Modules\Inventory\Repositories;

use App\Core\DTOs\TableQueryData;
use App\Core\Query\TableQueryApplier;
use App\Core\Security\TenantRecordScope;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly TableQueryApplier $tables,
        private readonly TenantRecordScope $records,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        $sorts = [
            'name' => 'products.name',
            'product_code' => 'products.product_code',
            'hs_code' => 'products.hs_code',
            'sku' => 'products.sku',
            'barcode' => 'products.barcode',
            'mrp' => 'products.mrp',
            'reorder_level' => 'products.reorder_level',
            'stock_on_hand' => 'stock_on_hand',
            'created_at' => 'products.created_at',
            'updated_at' => 'products.updated_at',
        ];

        $query = Product::query()
            ->select('products.*')
            ->with(['company:id,name', 'unit:id,name', 'division:id,name,code'])
            ->withSum(['batches as stock_on_hand' => fn ($query) => $query->where('is_active', true)], 'quantity_available');

        $this->tables->tenant($query, $user, 'products.tenant_id');
        $this->tables->softDeletes($query, $table);
        $this->tables->search($query, $table->search, [
            'products.name',
            'products.product_code',
            'products.hs_code',
            'products.generic_name',
            'products.sku',
            'products.barcode',
            'products.composition',
            'products.group_name',
            'products.manufacturer_name',
            'products.packaging_type',
            'products.keywords',
        ]);

        $query
            ->when(isset($table->filters['company_id']), fn (Builder $builder) => $builder->where('products.company_id', $table->filters['company_id']))
            ->when(isset($table->filters['division_id']), fn (Builder $builder) => $builder->where('products.division_id', $table->filters['division_id']));
        $this->tables->activeFilter($query, $table, 'products.is_active');

        $sortColumn = $this->tables->sortColumn($table, $sorts, 'updated_at');

        if ($sortColumn === 'stock_on_hand') {
            $query->orderByRaw('COALESCE(stock_on_hand, 0) '.$table->sortOrder);
        } else {
            $query->orderBy($sortColumn, $table->sortOrder);
        }

        return $this->tables->paginate($query, $table);
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

    public function findTrashed(int $id, ?User $user = null): Product
    {
        $query = Product::query()->onlyTrashed();

        if ($user) {
            $this->records->apply($query, $user);
        }

        return $query->findOrFail($id);
    }

    public function skuExists(?int $companyId, string $sku): bool
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('sku', $sku)
            ->exists();
    }
}
