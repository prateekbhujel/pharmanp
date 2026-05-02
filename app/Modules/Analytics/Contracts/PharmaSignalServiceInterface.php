<?php

namespace App\Modules\Analytics\Contracts;

use Illuminate\Http\Request;

interface PharmaSignalServiceInterface
{
    public function inventorySignals(Request $request, int $perPage = 20): array;
}
