<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Opaque refresh tokens: one active token per user and device, rotated on every use.
 * Only the SHA-256 of a token is stored.
 */
class RefreshTokens
{
    public function __construct(private AccessTokens $accessTokens) {}

    /**
     * Revoke the device's current token and issue a new token pair.
     *
     * @return array{token_type: string, access_token: string, expires_in: int, refresh_token: string, refresh_expires_in: int}
     */
    public function issuePair(User $user, string $deviceId): array
    {
        $plain = $this->generate();

        DB::transaction(function () use ($user, $deviceId, $plain) {
            $this->revokeDevice($user, $deviceId);

            $user->refreshTokens()->create([
                'device_id' => $deviceId,
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addSeconds($this->ttl()),
                'created_at' => now(),
            ]);
        });

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->accessTokens->issue($user, $deviceId),
            'expires_in' => $this->accessTokens->ttl(),
            'refresh_token' => $plain,
            'refresh_expires_in' => $this->ttl(),
        ];
    }

    /**
     * Consume a refresh token and return its user; the caller issues the new pair.
     * Reusing a revoked token revokes every token of that user on that device.
     */
    public function consume(string $plain, string $deviceId): User
    {
        $user = DB::transaction(function () use ($plain, $deviceId) {
            $token = RefreshToken::query()
                ->where('token_hash', hash('sha256', $plain))
                ->lockForUpdate()
                ->first();

            if ($token === null || $token->device_id !== $deviceId) {
                return null;
            }

            if ($token->revoked_at !== null) {
                $this->revokeDevice($token->user, $token->device_id);

                return null;
            }

            if ($token->expires_at->isPast()) {
                return null;
            }

            $token->update(['revoked_at' => now()]);

            return $token->user;
        });

        if ($user === null) {
            throw new ApiException(401, 'invalid_refresh_token', 'The refresh token is invalid.');
        }

        return $user;
    }

    public function revokeDevice(User $user, string $deviceId): void
    {
        $user->refreshTokens()
            ->where('device_id', $deviceId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAll(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function ttl(): int
    {
        return config('jwt.refresh_ttl');
    }
}
