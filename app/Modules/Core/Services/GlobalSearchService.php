<?php

namespace App\Modules\Core\Services;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Models\User;
use App\Modules\Core\DTOs\GlobalSearchData;
use App\Modules\Core\Repositories\Interfaces\GlobalSearchRepositoryInterface;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function __construct(private readonly GlobalSearchRepositoryInterface $searches) {}

    public function search(GlobalSearchData $data, ?User $user = null): Collection
    {
        return $this->searches->search($data->query, $user, $data->limit);
    }
}
