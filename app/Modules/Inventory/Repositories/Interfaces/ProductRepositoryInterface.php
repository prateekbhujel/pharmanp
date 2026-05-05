<?php

namespace App\Modules\Inventory\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Inventory\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function paginate(TableQueryData $table, ?User $user = null): LengthAwarePaginator;

    public function create(array $payload): Product;

    public function update(Product $product, array $payload): Product;

    public function findTrashed(int $id, ?User $user = null): Product;

    public function skuExists(?int $companyId, string $sku): bool;
}
