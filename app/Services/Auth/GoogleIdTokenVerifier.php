<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies Google ID tokens against Google's JWKS, `iss`, `exp` and `aud`.
 */
class GoogleIdTokenVerifier
{
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    private const CACHE_KEY = 'google_jwks';

    private const LEEWAY_SECONDS = 60;

    /**
     * @return array{sub: string, email: string|null, email_verified: bool, name: string|null}
     */
    public function verify(string $idToken): array
    {
        $claims = $this->decode($idToken, refreshKeys: false) ?? $this->decode($idToken, refreshKeys: true);

        $audience = $claims['aud'] ?? null;

        if ($claims === null
            || ! in_array($claims['iss'] ?? null, self::ISSUERS, true)
            || ! is_string($audience)
            || ! in_array($audience, config('services.google.client_ids'), true)
            || empty($claims['sub'])) {
            throw new ApiException(401, 'invalid_google_token', 'The Google token is invalid.');
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => isset($claims['email']) ? strtolower((string) $claims['email']) : null,
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => isset($claims['name']) ? (string) $claims['name'] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(string $idToken, bool $refreshKeys): ?array
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            return (array) JWT::decode($idToken, JWK::parseKeySet($this->keys($refreshKeys), 'RS256'));
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * Google rotates its keys; a failed verification retries once with freshly fetched keys.
     *
     * @return array<string, mixed>
     */
    private function keys(bool $refresh): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return Http::timeout(5)->retry(2, 200)->get(config('services.google.jwks_url'))->throw()->json();
        });
    }
}
