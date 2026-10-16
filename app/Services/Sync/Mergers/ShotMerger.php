<?php

namespace App\Services\Sync\Mergers;

use App\Models\Category;
use App\Models\Shot;
use App\Models\User;
use App\Services\Sync\RejectedItem;
use App\Services\Sync\SeqAllocator;
use App\Support\Timestamp;
use Carbon\CarbonImmutable;

/**
 * New id → insert. Existing id → immutable fields are ignored; `(undone_at, deleted_at)` are
 * replaced when `state_changed_at` is newer and `(note, category_id)` when `meta_changed_at` is
 * newer. Ties keep the server value.
 */
class ShotMerger implements Merger
{
    private const MAX_FUTURE_HOURS = 24;

    public function entity(): string
    {
        return 'shot';
    }

    public function idOf(array $item): string
    {
        return is_string($item['id'] ?? null) ? strtolower($item['id']) : '';
    }

    public function rules(): array
    {
        return [
            'id' => Rules::uuid(),
            'color' => ['required', 'in:white,black'],
            'x' => ['required', 'numeric', 'between:0,400'],
            'y' => ['required', 'numeric', 'between:0,460'],
            'r' => ['required', 'numeric', 'between:3,40'],
            'intensity' => ['required', 'integer', 'between:10,100'],
            'shot_at' => Rules::timestamp(),
            'undone_at' => Rules::timestamp(nullable: true),
            'deleted_at' => Rules::timestamp(nullable: true),
            'state_changed_at' => Rules::timestamp(),
            'note' => ['present', 'nullable', 'string', 'max:500'],
            'category_id' => Rules::uuid(nullable: true),
            'meta_changed_at' => Rules::timestamp(),
            'device_id' => Rules::uuid(),
        ];
    }

    public function merge(User $user, array $item, SeqAllocator $seq): void
    {
        $id = $this->idOf($item);
        $shot = Shot::find($id);

        if ($shot !== null && (int) $shot->user_id !== $user->id) {
            throw new RejectedItem('id_conflict');
        }

        $state = [
            'undone_at' => Timestamp::parse($item['undone_at']),
            'deleted_at' => Timestamp::parse($item['deleted_at']),
            'state_changed_at' => Timestamp::parse($item['state_changed_at']),
        ];

        if ($shot === null) {
            $shotAt = Timestamp::parse($item['shot_at']);

            if ($shotAt->gt(now()->addHours(self::MAX_FUTURE_HOURS))) {
                throw new RejectedItem('shot_in_future');
            }

            // Check the category before allocating a seq, so a rejection leaves no gap.
            $meta = $this->meta($user, $item, $item['color']);

            Shot::create([
                'id' => $id,
                'user_id' => $user->id,
                'color' => $item['color'],
                'x' => round((float) $item['x'], 2),
                'y' => round((float) $item['y'], 2),
                'r' => round((float) $item['r'], 2),
                'intensity' => (int) $item['intensity'],
                'shot_at' => $shotAt,
                'device_id' => strtolower($item['device_id']),
                'seq' => $seq->next(),
            ] + $state + $meta);

            return;
        }

        $changed = false;

        if ($state['state_changed_at']->gt($shot->state_changed_at)) {
            $shot->fill($state);
            $changed = true;
        }

        if (Timestamp::parse($item['meta_changed_at'])->gt($shot->meta_changed_at)) {
            $shot->fill($this->meta($user, $item, $shot->color));
            $changed = true;
        }

        if ($changed) {
            $shot->fill(['seq' => $seq->next()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{note: string|null, category_id: string|null, meta_changed_at: CarbonImmutable}
     *
     * @throws RejectedItem
     */
    private function meta(User $user, array $item, string $color): array
    {
        $categoryId = $item['category_id'] === null ? null : strtolower($item['category_id']);

        if ($categoryId !== null) {
            $category = Category::where('user_id', $user->id)->find($categoryId);

            if ($category === null) {
                throw new RejectedItem('category_not_found');
            }

            if ($category->kind !== $color) {
                throw new RejectedItem('category_kind_mismatch');
            }
        }

        return [
            'note' => $item['note'],
            'category_id' => $categoryId,
            'meta_changed_at' => Timestamp::parse($item['meta_changed_at']),
        ];
    }
}
