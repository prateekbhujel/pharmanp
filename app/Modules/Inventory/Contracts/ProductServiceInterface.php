<?php

namespace App\Modules\Inventory\Contracts;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\DTOs\ProductData;
use App\Modules\Inventory\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

interface ProductServiceInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function create(ProductData $data, ?User $user = null, ?UploadedFile $image = null): Product;

    public function update(Product $product, ProductData $data, ?User $user = null, ?UploadedFile $image = null, bool $removeImage = false): Product;

    public function delete(Product $product, ?User $user = null): void;

    public function restore(int $id, ?User $user = null): Product;
}
