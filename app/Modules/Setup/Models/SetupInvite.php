<?php

namespace App\Modules\Setup\Models;

use Illuminate\Database\Eloquent\Model;

class SetupInvite extends Model
{
    protected $fillable = [
        'token_hash',
        'client_name',
        'client_email',
        'status',
        'requested_features',
        'prefill',
        'expires_on',
        'used_at',
        'tenant_id',
        'company_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'requested_features' => 'array',
            'prefill' => 'array',
            'expires_on' => 'date',
            'used_at' => 'datetime',
        ];
    }
}
