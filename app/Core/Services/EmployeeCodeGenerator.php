<?php

namespace App\Core\Services;

class EmployeeCodeGenerator
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function next(): string
    {
        return $this->numbers->next('employee', 'employees', null);
    }
}
