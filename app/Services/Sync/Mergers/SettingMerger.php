<?php

namespace App\Services\Sync\Mergers;

use App\Models\User;
use App\Models\UserSetting;
use App\Services\Sync\RejectedItem;
use App\Services\Sync\SeqAllocator;
use App\Support\Timestamp;

/**
 * New key → insert. Existing key → replaced when `changed_at` is newer. Ties keep the server value.
 */
class SettingMerger implements Merger
{
    public function entity(): string
    {
        return 'setting';
    }

    public function idOf(array $item): string
    {
        return is_string($item['key'] ?? null) ? $item['key'] : '';
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'value' => ['present'],
            'changed_at' => Rules::timestamp(),
        ];
    }

    public function merge(User $user, array $item, SeqAllocator $seq): void
    {
        $key = $item['key'];

        if (! in_array($key, UserSetting::KEYS, true)) {
            throw new RejectedItem('unknown_setting');
        }

        $value = $item['value'];
        $valid = match ($key) {
            'dot_size' => is_int($value) && $value >= 3 && $value <= 40,
            'intensity' => is_int($value) && $value >= 10 && $value <= 100,
            'sound_enabled' => is_bool($value),
        };

        if (! $valid) {
            throw new RejectedItem('validation_failed', ['value' => ["The value is invalid for {$key}."]]);
        }

        $changedAt = Timestamp::parse($item['changed_at']);
        $setting = UserSetting::where('user_id', $user->id)->where('key', $key)->first();

        if ($setting === null) {
            UserSetting::create(['user_id' => $user->id, 'key' => $key, 'value' => $value, 'changed_at' => $changedAt, 'seq' => $seq->next()]);

            return;
        }

        if ($changedAt->gt($setting->changed_at)) {
            $setting->fill(['value' => $value, 'changed_at' => $changedAt, 'seq' => $seq->next()])->save();
        }
    }
}
