<?php

namespace App\Modules\Inventory\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'name',
        'legal_name',
        'pan_number',
        'phone',
        'email',
        'address',
        'country',
        'company_type',
        'default_cc_rate',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'default_cc_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
