<?php

namespace App\Modules\ImportExport\Repositories\Interfaces;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface ExportRepositoryInterface
{
    public function inventoryMasterRows(Request $request, string $master): Collection;

    public function inventoryProductRows(Request $request): Collection;

    public function inventoryBatchRows(Request $request): Collection;

    public function datasetRows(Request $request, string $dataset): Collection;
}
