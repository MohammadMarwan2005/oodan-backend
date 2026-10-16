<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Records the devices a user signs in and syncs from (diagnostics and activity stats).
 */
class Devices
{
    /**
     * Upsert the request's device (from the validated device headers) for the user.
     * A device id that moves to another account is reassigned to it.
     */
    public function touch(User $user, Request $request, ?int $pulledSeq = null): void
    {
        $now = now();
        $update = [
            'user_id' => $user->id,
            'platform' => $request->attributes->get('platform'),
            'app_version' => mb_substr((string) $request->header('X-App-Version'), 0, 50) ?: null,
            'last_seen_at' => $now,
            'updated_at' => $now,
        ];

        if ($pulledSeq !== null) {
            $update['last_pulled_seq'] = $pulledSeq;
        }

        Device::upsert(
            [['id' => $request->attributes->get('device_id'), 'last_pulled_seq' => $pulledSeq ?? 0, 'created_at' => $now] + $update],
            ['id'],
            array_keys($update),
        );
    }
}
