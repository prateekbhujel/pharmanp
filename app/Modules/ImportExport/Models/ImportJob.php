<?php

namespace App\Modules\ImportExport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'target',
        'original_filename',
        'stored_path',
        'detected_columns',
        'mapping',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'detected_columns' => 'array',
            'mapping' => 'array',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportStagedRow::class);
    }
}
