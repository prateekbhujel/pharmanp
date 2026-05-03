<?php

namespace App\Modules\ImportExport\Repositories\Interfaces;

use App\Modules\ImportExport\DTOs\ImportJobData;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Models\ImportStagedRow;
use Illuminate\Support\Collection;

interface ImportJobRepositoryInterface
{
    public function createJob(ImportJobData $data): ImportJob;

    public function lockJob(int $id): ImportJob;

    public function updateJob(ImportJob $job, array $data): ImportJob;

    public function clearRows(ImportJob $job): void;

    public function createRow(ImportJob $job, int $rowNumber, array $rawData, ?array $mappedData = null, ?array $errors = null, string $status = 'pending'): ImportStagedRow;

    public function invalidRows(ImportJob $job): Collection;

    public function freshWithRows(ImportJob $job): ImportJob;
}
