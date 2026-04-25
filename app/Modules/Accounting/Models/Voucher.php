<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'voucher_no',
        'voucher_date',
        'voucher_type',
        'total_amount',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'voucher_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(VoucherEntry::class);
    }
}
