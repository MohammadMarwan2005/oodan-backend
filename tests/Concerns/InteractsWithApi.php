<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Services\Auth\AccessTokens;

trait InteractsWithApi
{
    protected string $deviceId = '0192f0a0-1111-7000-8000-000000000001';

    /**
     * @return array<string, string>
     */
    protected function deviceHeaders(?string $deviceId = null): array
    {
        return [
            'X-Device-Id' => $deviceId ?? $this->deviceId,
            'X-Platform' => 'android',
            'X-App-Version' => '1.0.0',
        ];
    }

    /**
     * Device headers plus a bearer access token for the user.
     *
     * @return array<string, string>
     */
    protected function authHeaders(User $user, ?string $deviceId = null): array
    {
        $deviceId ??= $this->deviceId;

        return $this->deviceHeaders($deviceId) + [
            'Authorization' => 'Bearer '.app(AccessTokens::class)->issue($user, $deviceId),
        ];
    }
}
