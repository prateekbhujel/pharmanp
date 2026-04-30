<?php

namespace App\Modules\Inventory\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\DTOs\ProductData;
use App\Modules\Inventory\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
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
            ->with(['company:id,name', 'unit:id,name', 'category:id,name'])
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

    public function create(ProductData $data, ?User $user = null, ?UploadedFile $image = null): Product
    {
        return DB::transaction(function () use ($data, $user, $image) {
            $payload = $data->toArray();
            $payload['sku'] = $payload['sku'] ?: $this->nextSku($payload['company_id']);
            $payload['tenant_id'] = $user?->tenant_id;
            $payload['created_by'] = $user?->id;
            $payload['updated_by'] = $user?->id;
            $payload['image_path'] = $image ? $this->storeImage($image) : null;

            return Product::query()->create($payload)->fresh(['company', 'unit', 'category']);
        });
    }

    public function update(Product $product, ProductData $data, ?User $user = null, ?UploadedFile $image = null, bool $removeImage = false): Product
    {
        return DB::transaction(function () use ($product, $data, $user, $image, $removeImage) {
            $payload = $data->toArray();
            $payload['sku'] = $payload['sku'] ?: $product->sku ?: $this->nextSku($payload['company_id']);
            $payload['tenant_id'] = $product->tenant_id ?: $user?->tenant_id;
            $payload['updated_by'] = $user?->id;

            if ($image || $removeImage) {
                $this->deleteImage($product->image_path);
                $payload['image_path'] = $image ? $this->storeImage($image) : null;
            }

            $product->update($payload);

            return $product->fresh(['company', 'unit', 'category']);
        });
    }

    public function delete(Product $product, ?User $user = null): void
    {
        DB::transaction(function () use ($product, $user) {
            $product->forceFill([
                'deleted_by' => $user?->id,
                'is_active' => false,
            ])->save();

            $product->delete();
        });
    }

    public function restore(int $id, ?User $user = null): Product
    {
        return DB::transaction(function () use ($id, $user) {
            $product = Product::query()->onlyTrashed()->findOrFail($id);
            $product->restore();
            $product->forceFill([
                'deleted_by' => null,
                'is_active' => true,
                'updated_by' => $user?->id,
            ])->save();

            return $product->fresh(['company', 'unit', 'category']);
        });
    }

    private function applyFilters(Builder $query, TableQueryData $table): void
    {
        $query
            ->when($table->search, function (Builder $builder, string $search) {
                $builder->where(function (Builder $inner) use ($search) {
                    $inner->where('products.name', 'like', '%'.$search.'%')
                        ->orWhere('products.product_code', 'like', '%'.$search.'%')
                        ->orWhere('products.generic_name', 'like', '%'.$search.'%')
                        ->orWhere('products.sku', 'like', '%'.$search.'%')
                        ->orWhere('products.barcode', 'like', '%'.$search.'%')
                        ->orWhere('products.composition', 'like', '%'.$search.'%')
                        ->orWhere('products.group_name', 'like', '%'.$search.'%')
                        ->orWhere('products.manufacturer_name', 'like', '%'.$search.'%');
                });
            })
            ->when(isset($table->filters['company_id']), fn (Builder $builder) => $builder->where('products.company_id', $table->filters['company_id']))
            ->when(isset($table->filters['category_id']), fn (Builder $builder) => $builder->where('products.category_id', $table->filters['category_id']))
            ->when(isset($table->filters['is_active']), fn (Builder $builder) => $builder->where('products.is_active', (bool) $table->filters['is_active']));
    }

    private function nextSku(?int $companyId): string
    {
        $prefix = $companyId ? 'P'.$companyId : 'PNP';
        $suffix = Str::upper(Str::random(6));

        while (Product::query()->where('company_id', $companyId)->where('sku', $prefix.'-'.$suffix)->exists()) {
            $suffix = Str::upper(Str::random(6));
        }

        return $prefix.'-'.$suffix;
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store('products', 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
