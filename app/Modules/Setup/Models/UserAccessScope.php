<?php

namespace App\Modules\Setup\Models;

use App\Core\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessScope extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'own',
        'subordinate',
        'branch',
        'area',
        'division',
        'company',
        'all',
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'user_id',
        'scope_type',
        'scope_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
