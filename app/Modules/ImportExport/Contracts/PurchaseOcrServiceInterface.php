<?php

namespace App\Modules\ImportExport\Contracts;

use Illuminate\Http\UploadedFile;

interface PurchaseOcrServiceInterface
{
    public function extract(UploadedFile $file): array;

    public function draftPurchase(array $data): array;
}
