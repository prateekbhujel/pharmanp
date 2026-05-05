<?php

namespace App\Core\Services;

use App\Models\User;

class ProductCodeGenerator
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function next(?User $user = null): string
    {
        return $this->numbers->next('product', 'products', null, $user);
    }
}
