<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a valid `X-Device-Id` (UUID) and `X-Platform` (android|ios|web).
 * The normalised values are stored as the `device_id` and `platform` request attributes.
 */
class RequireDeviceHeaders
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = strtolower((string) $request->header('X-Device-Id'));
        $platform = strtolower((string) $request->header('X-Platform'));

        if (! Str::isUuid($deviceId) || ! in_array($platform, self::PLATFORMS, true)) {
            throw new ApiException(400, 'invalid_device_headers', 'X-Device-Id (UUID) and X-Platform (android, ios, web) are required.');
        }

        $request->attributes->set('device_id', $deviceId);
        $request->attributes->set('platform', $platform);

        return $next($request);
    }
}
