<?php

namespace App\Core\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class JwtTokenService
{
    public function issue(User $user, ?CarbonInterface $expiresAt = null, array $abilities = ['*']): string
    {
        $now = CarbonImmutable::now();
        $expiresAt ??= $now->addMinutes(max((int) config('pharmanp.jwt.ttl_minutes', 1440), 5));

        return $this->encode([
            'iss' => (string) config('pharmanp.jwt.issuer', config('app.url')),
            'aud' => (string) config('pharmanp.jwt.audience', 'pharmanp-api'),
            'sub' => (string) $user->getKey(),
            'email' => $user->email,
            'abilities' => $abilities,
            'iat' => $now->timestamp,
            'nbf' => $now->timestamp,
            'exp' => $expiresAt->getTimestamp(),
            'jti' => (string) Str::uuid(),
        ]);
    }

    public function userForToken(?string $token): ?User
    {
        $payload = $this->payload($token);

        if (! $payload) {
            return null;
        }

        $user = User::query()->find($payload['sub'] ?? null);

        return $user?->is_active ? $user : null;
    }

    public function payload(?string $token): ?array
    {
        if (! $this->looksLikeJwt($token)) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $signature] = explode('.', (string) $token);
        $expected = $this->sign($encodedHeader.'.'.$encodedPayload);

        if (! hash_equals($expected, $signature)) {
            return null;
        }

        try {
            $header = json_decode($this->base64UrlDecode($encodedHeader), true, flags: JSON_THROW_ON_ERROR);
            $payload = json_decode($this->base64UrlDecode($encodedPayload), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            return null;
        }

        $now = CarbonImmutable::now()->timestamp;

        if (($payload['nbf'] ?? 0) > $now || ($payload['exp'] ?? 0) <= $now) {
            return null;
        }

        if (($payload['aud'] ?? null) !== config('pharmanp.jwt.audience', 'pharmanp-api')) {
            return null;
        }

        return $payload;
    }

    public function looksLikeJwt(?string $token): bool
    {
        return is_string($token) && substr_count($token, '.') === 2;
    }

    private function encode(array $payload): string
    {
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$body;

        return $unsigned.'.'.$this->sign($unsigned);
    }

    private function sign(string $value): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $value, $this->secret(), true));
    }

    private function secret(): string
    {
        $secret = (string) (config('pharmanp.jwt.secret') ?: config('app.key'));

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded ?: $secret;
        }

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
