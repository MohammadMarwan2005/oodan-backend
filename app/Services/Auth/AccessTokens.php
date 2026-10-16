<?php

namespace App\Services\Auth;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/**
 * Short-lived HS256 JWT access tokens with `sub`, `iat`, `exp` and `device_id` claims.
 */
class AccessTokens
{
    private const ALGORITHM = 'HS256';

    public function issue(User $user, string $deviceId): string
    {
        $now = now()->getTimestamp();

        return JWT::encode([
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'exp' => $now + $this->ttl(),
            'device_id' => $deviceId,
        ], $this->secret(), self::ALGORITHM);
    }

    /**
     * @return array{sub: string, device_id: string}|null
     */
    public function decode(string $token): ?array
    {
        // Validate `exp` against the application clock so it follows Carbon test time.
        JWT::$timestamp = now()->getTimestamp();

        try {
            $claims = (array) JWT::decode($token, new Key($this->secret(), self::ALGORITHM));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = null;
        }

        if (! isset($claims['sub'], $claims['device_id']) || ! is_string($claims['device_id'])) {
            return null;
        }

        return ['sub' => (string) $claims['sub'], 'device_id' => $claims['device_id']];
    }

    public function ttl(): int
    {
        return config('jwt.ttl');
    }

    private function secret(): string
    {
        return config('jwt.secret') ?: throw new RuntimeException('JWT_SECRET is not set.');
    }
}
