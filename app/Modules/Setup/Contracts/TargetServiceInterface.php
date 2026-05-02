<?php

namespace App\Modules\Setup\Contracts;

use App\Core\DTOs\TableQueryData;
use App\Models\User;
use App\Modules\Setup\Models\Target;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TargetServiceInterface
{
    public function targets(TableQueryData $table, User $user): LengthAwarePaginator;

    public function save(Target $target, array $data, User $user): Target;

    public function delete(Target $target, User $user): void;
}
