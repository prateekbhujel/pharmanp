<?php

namespace App\Modules\Setup\Services;

use App\Models\User;
use App\Modules\Setup\Models\SetupInvite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SetupInviteService
{
    public function create(array $data, User $user): array
    {
        $plainToken = Str::random(48);

        $invite = DB::transaction(fn () => SetupInvite::query()->create([
            'token_hash' => hash('sha256', $plainToken),
            'client_name' => $data['client_name'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'requested_features' => $data['requested_features'] ?? [],
            'prefill' => $data['prefill'] ?? [],
            'expires_on' => $data['expires_on'] ?? now()->addDays(7)->toDateString(),
            'created_by' => $user->id,
        ]));

        return [
            'id' => $invite->id,
            'token' => $plainToken,
            'setup_url' => url('/setup?invite='.$plainToken),
            'expires_on' => $invite->expires_on?->toDateString(),
        ];
    }

    public function revoke(SetupInvite $invite): SetupInvite
    {
        $invite->forceFill(['status' => 'revoked'])->save();

        return $invite;
    }
}
