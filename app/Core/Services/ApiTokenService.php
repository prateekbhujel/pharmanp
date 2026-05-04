<?php

namespace App\Core\Services;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ApiTokenService
{
    public function create(User $user, string $name, ?CarbonInterface $expiresAt = null, array $abilities = ['*'], ?User $createdBy = null): array
    {
        $plainTextToken = 'pnp_'.Str::random(72);

        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'token_hash' => $this->hash($plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy?->id,
            'updated_by' => $createdBy?->id,
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'token' => $token,
        ];
    }

    public function userForToken(?string $plainTextToken): ?User
    {
        if (! filled($plainTextToken)) {
            return null;
        }

        $token = ApiToken::query()
            ->with('user')
            ->where('token_hash', $this->hash($plainTextToken))
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $token?->user || ! $token->user->is_active) {
            return null;
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $token->user;
    }

    public function revoke(ApiToken $token, ?User $updatedBy = null): void
    {
        $token->forceFill([
            'revoked_at' => now(),
            'updated_by' => $updatedBy?->id,
        ])->save();
    }

    public function revokePlainTextToken(?string $plainTextToken, ?User $updatedBy = null): bool
    {
        if (! filled($plainTextToken)) {
            return false;
        }

        $token = ApiToken::query()
            ->where('token_hash', $this->hash($plainTextToken))
            ->whereNull('revoked_at')
            ->first();

        if (! $token) {
            return false;
        }

        $this->revoke($token, $updatedBy);

        return true;
    }

    public function expiryForFrontendLogin(): CarbonInterface
    {
        return now()->addDays(30);
    }

    public function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }
}
