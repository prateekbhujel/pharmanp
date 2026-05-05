<?php

namespace App\Modules\MR\Models;

use App\Core\Traits\BelongsToTenant;
use App\Core\Traits\HasFiscalYear;



use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use BelongsToTenant, SoftDeletes;


    protected $table = 'branches';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'store_id',
        'name',
        'code',
        'type',
        'parent_id',
        'address',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
return [
            'is_active' => 'boolean',
        ];
    }

    // The parent HQ of a sub-branch (null if this IS the HQ).
    public function parent(): BelongsTo
    {
return $this->belongsTo(Branch::class, 'parent_id');
    }

    // Sub-branches under this HQ.
    public function children(): HasMany
    {
return $this->hasMany(Branch::class, 'parent_id');
    }

    // MRs assigned to this branch.
    public function medicalRepresentatives(): HasMany
    {
return $this->hasMany(MedicalRepresentative::class, 'branch_id');
    }

    public function getIsHqAttribute(): bool
    {
return $this->type === 'hq';
    }
}
