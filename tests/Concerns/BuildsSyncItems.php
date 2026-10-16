<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;

trait BuildsSyncItems
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function shotItem(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'color' => 'white',
            'x' => 120.5,
            'y' => 200.25,
            'r' => 14,
            'intensity' => 80,
            'shot_at' => '2026-10-16T20:00:00.000Z',
            'undone_at' => null,
            'deleted_at' => null,
            'state_changed_at' => '2026-10-16T20:00:00.000Z',
            'note' => null,
            'category_id' => null,
            'meta_changed_at' => '2026-10-16T20:00:00.000Z',
            'device_id' => $this->deviceId,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function categoryItem(array $overrides = []): array
    {
        return array_merge([
            'id' => (string) Str::uuid7(),
            'kind' => 'white',
            'name' => 'ذكر',
            'color' => '#22AA66',
            'icon' => 'beads',
            'preset_key' => 'dhikr',
            'sort' => 1,
            'changed_at' => '2026-10-16T20:00:00.000Z',
            'deleted_at' => null,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function settingItem(string $key, mixed $value, string $changedAt = '2026-10-16T20:00:00.000Z'): array
    {
        return ['key' => $key, 'value' => $value, 'changed_at' => $changedAt];
    }
}
