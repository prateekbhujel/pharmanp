<?php

namespace App\Core\Services;

use App\Models\User;

class SupplierCodeGenerator
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function next(?User $user = null): string
    {
        return $this->numbers->next('supplier', 'suppliers', null, $user);
    }
}
