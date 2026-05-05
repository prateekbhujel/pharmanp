<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'starts_on',
        'ends_on',
        'is_current',
        'status',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }
}
