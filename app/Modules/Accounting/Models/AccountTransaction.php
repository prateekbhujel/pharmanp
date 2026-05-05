<?php

namespace App\Modules\Accounting\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
class AccountTransaction extends Model
{
    use BelongsToTenant, HasFiscalYear;


protected $fillable = [
        'tenant_id',
        'company_id',
        'fiscal_year_id',
        'transaction_date',
        'account_type',
        'party_type',
        'party_id',
        'source_type',
        'source_id',
        'debit',
        'credit',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
return [
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
        ];
    }
}
