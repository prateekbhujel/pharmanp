<?php

namespace App\Modules\MR\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use App\Modules\Party\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepresentativeVisit extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'company_id',
        'medical_representative_id',
        'employee_id',
        'customer_id',
        'visit_date',
        'visit_time',
        'status',
        'purpose',
        'order_value',
        'notes',
        'remarks',
        'latitude',
        'longitude',
        'location_name',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'visit_date'  => 'date',
            'order_value' => 'decimal:2',
            'latitude'    => 'decimal:7',
            'longitude'   => 'decimal:7',
        ];
    }

    public function medicalRepresentative(): BelongsTo
    {
return $this->belongsTo(MedicalRepresentative::class);
    }

    public function customer(): BelongsTo
    {
return $this->belongsTo(Customer::class);
    }

    public function employee(): BelongsTo
    {
return $this->belongsTo(\App\Modules\Setup\Models\Employee::class);
    }
}
