<?php

namespace App\Modules\Setup\Contracts;

interface SetupServiceInterface
{
    public function complete(array $data): array;
}
