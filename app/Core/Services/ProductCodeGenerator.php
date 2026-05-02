<?php

namespace App\Core\Services;

class ProductCodeGenerator
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function next(): string
    {
        return $this->numbers->next('product', 'products');
    }
}
