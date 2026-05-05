<?php

namespace App\Modules\Party\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use App\Modules\Setup\Models\SupplierType;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'company_id',
        'supplier_type_id',
        'supplier_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'pan_number',
        'address',
        'opening_balance',
        'current_balance',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function supplierType()
    {
return $this->belongsTo(SupplierType::class, 'supplier_type_id');
    }
}
