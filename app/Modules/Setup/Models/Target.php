<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use SoftDeletes;

    public const TYPES = ['primary', 'secondary'];
    public const PERIODS = ['monthly', 'quarterly', 'annual'];
    public const LEVELS = ['company', 'division', 'area', 'employee', 'product'];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'area_id',
        'division_id',
        'employee_id',
        'product_id',
        'target_type',
        'target_period',
        'target_level',
        'target_amount',
        'target_quantity',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_quantity' => 'decimal:3',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }
}
