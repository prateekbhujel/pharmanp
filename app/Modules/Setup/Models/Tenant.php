<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'plan_code',
        'trial_ends_on',
        'suspended_at',
        'suspension_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_on' => 'date',
            'suspended_at' => 'datetime',
        ];
    }
}
