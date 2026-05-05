<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use App\Models\User;
use App\Modules\MR\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'company_id',
        'user_id',
        'branch_id',
        'area_id',
        'division_id',
        'reports_to_employee_id',
        'employee_code',
        'name',
        'designation',
        'phone',
        'email',
        'joined_on',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
return [
            'joined_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function area(): BelongsTo
    {
return $this->belongsTo(Area::class);
    }

    public function division(): BelongsTo
    {
return $this->belongsTo(Division::class);
    }

    public function manager(): BelongsTo
    {
return $this->belongsTo(self::class, 'reports_to_employee_id');
    }

    public function subordinates(): HasMany
    {
return $this->hasMany(self::class, 'reports_to_employee_id');
    }
}
