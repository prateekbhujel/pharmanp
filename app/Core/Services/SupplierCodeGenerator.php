<?php

namespace App\Core\Services;

class SupplierCodeGenerator
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function next(): string
    {
        return $this->numbers->next('supplier', 'suppliers');
    }
}
