<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use App\Modules\Inventory\Models\Product;
use App\Modules\MR\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use BelongsToTenant, SoftDeletes;

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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
