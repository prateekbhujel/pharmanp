<?php

namespace App\Modules\ImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportStagedRow extends Model
{
    protected $fillable = [
        'import_job_id',
        'row_number',
        'raw_data',
        'mapped_data',
        'errors',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
            'mapped_data' => 'array',
            'errors' => 'array',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
