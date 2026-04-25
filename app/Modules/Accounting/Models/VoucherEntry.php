<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherEntry extends Model
{
    protected $fillable = [
        'voucher_id',
        'line_no',
        'account_type',
        'party_type',
        'party_id',
        'entry_type',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
