<?php

namespace App\Modules\Accounting\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentBillAllocation extends Model
{
    use BelongsToTenant, HasFiscalYear;



protected $fillable = [
        'payment_id',
        'bill_id',
        'bill_type',
        'allocated_amount',
    ];

    protected function casts(): array
    {

return [
            'allocated_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {

return $this->belongsTo(Payment::class);
    }
}
