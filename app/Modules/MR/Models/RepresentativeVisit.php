<?php

namespace App\Modules\MR\Models;

use App\Modules\Party\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepresentativeVisit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'medical_representative_id',
        'customer_id',
        'visit_date',
        'status',
        'order_value',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'order_value' => 'decimal:2',
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
}
