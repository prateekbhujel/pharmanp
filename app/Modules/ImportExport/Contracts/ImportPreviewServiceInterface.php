<?php

namespace App\Modules\ImportExport\Contracts;

use App\Models\User;
use App\Modules\ImportExport\Models\ImportJob;
use Illuminate\Http\UploadedFile;

interface ImportPreviewServiceInterface
{
    public function preview(string $target, UploadedFile $file, ?int $userId = null): ImportJob;

    public function confirm(int $jobId, array $mapping, ?User $user = null): ImportJob;

    public function targetFields(): array;

    public function requiredFields(string $target): array;

    public function sampleCsv(string $target): string;

    public function rejectedCsv(ImportJob $job): string;
}
