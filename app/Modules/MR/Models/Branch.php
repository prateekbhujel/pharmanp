<?php

namespace App\Modules\MR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $table = 'branches';

    protected $guarded = [];

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
