<?php

namespace App\Modules\ImportExport\Repositories;

use App\Modules\ImportExport\DTOs\ImportJobData;
use App\Modules\ImportExport\Models\ImportJob;
use App\Modules\ImportExport\Models\ImportStagedRow;
use App\Modules\ImportExport\Repositories\Interfaces\ImportJobRepositoryInterface;
use Illuminate\Support\Collection;

class ImportJobRepository implements ImportJobRepositoryInterface
{
    public function createJob(ImportJobData $data): ImportJob
    {
        return ImportJob::query()->create($data->toArray());
    }

    public function lockJob(int $id): ImportJob
    {
        return ImportJob::query()->lockForUpdate()->findOrFail($id);
    }

    public function updateJob(ImportJob $job, array $data): ImportJob
    {
        $job->update($data);

        return $job;
    }

    public function clearRows(ImportJob $job): void
    {
        $job->rows()->delete();
    }

    public function createRow(ImportJob $job, int $rowNumber, array $rawData, ?array $mappedData = null, ?array $errors = null, string $status = 'pending'): ImportStagedRow
    {
        return ImportStagedRow::query()->create([
            'import_job_id' => $job->id,
            'row_number' => $rowNumber,
            'raw_data' => $rawData,
            'mapped_data' => $mappedData,
            'errors' => $errors,
            'status' => $status,
        ]);
    }

    public function invalidRows(ImportJob $job): Collection
    {
        return $job->rows()
            ->where('status', 'invalid')
            ->orderBy('row_number')
            ->get();
    }

    public function freshWithRows(ImportJob $job): ImportJob
    {
        return $job->fresh('rows');
    }
}
