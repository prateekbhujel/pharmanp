<?php

namespace App\Modules\Inventory\Services;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Contracts\ProductServiceInterface;
use App\Modules\Inventory\DTOs\ProductData;
use App\Modules\Inventory\Models\Product;
use App\Modules\Inventory\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService implements ProductServiceInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
    ) {}

    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator
    {
        return $this->products->paginate($table, $user);
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

            return $this->products->create($payload)->fresh(['company', 'unit', 'category']);
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

            $this->products->update($product, $payload);

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
            $product = $this->products->findTrashed($id);
            $product->restore();
            $product->forceFill([
                'deleted_by' => null,
                'is_active' => true,
                'updated_by' => $user?->id,
            ])->save();

            return $product->fresh(['company', 'unit', 'category']);
        });
    }

    private function nextSku(?int $companyId): string
    {
        $prefix = $companyId ? 'P'.$companyId : 'PNP';
        $suffix = Str::upper(Str::random(6));

        while ($this->products->skuExists($companyId, $prefix.'-'.$suffix)) {
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
