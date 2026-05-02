<?php

namespace App\Modules\Party\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Setup\Models\PartyType;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'party_type_id',
        'customer_code',
        'name',
        'contact_person',
        'phone',
        'email',
        'pan_number',
        'address',
        'credit_limit',
        'opening_balance',
        'current_balance',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function partyType()
    {
        return $this->belongsTo(PartyType::class, 'party_type_id');
    }
}
