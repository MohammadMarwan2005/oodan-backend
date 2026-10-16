<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Serves a fake Google JWKS and signs ID tokens with the matching private key.
 */
trait FakesGoogleTokens
{
    private static ?string $googlePrivateKey = null;

    private static array $googleJwks = [];

    protected function fakeGoogleJwks(): void
    {
        if (self::$googlePrivateKey === null) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $privatePem);
            $details = openssl_pkey_get_details($key)['rsa'];

            self::$googlePrivateKey = $privatePem;
            self::$googleJwks = ['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => 'test-key',
                'n' => rtrim(strtr(base64_encode($details['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['e']), '+/', '-_'), '='),
            ]]];
        }

        Http::fake([config('services.google.jwks_url') => Http::response(self::$googleJwks)]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function googleIdToken(array $overrides = []): string
    {
        $this->fakeGoogleJwks();

        return JWT::encode(array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'android-client.apps.googleusercontent.com',
            'sub' => '110000000000000000001',
            'email' => 'person@gmail.com',
            'email_verified' => true,
            'name' => 'Person',
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides), self::$googlePrivateKey, 'RS256', 'test-key');
    }
}
