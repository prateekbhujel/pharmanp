<?php

namespace App\Modules\Setup\Repositories\Interfaces;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;

use App\Modules\Setup\Models\DropdownOption;
use Illuminate\Support\Collection;

interface DropdownOptionRepositoryInterface
{
    public function managed(?string $alias = null): Collection;

    public function create(array $data): DropdownOption;

    public function update(DropdownOption $option, array $data): DropdownOption;

    public function updateStatus(DropdownOption $option, bool $active): DropdownOption;

    public function delete(DropdownOption $option): void;

    public function linkedUsageCount(DropdownOption $option): int;
}
