<?php

namespace App\Services\Sync;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Sync\Mergers\CategoryMerger;
use App\Services\Sync\Mergers\Merger;
use App\Services\Sync\Mergers\SettingMerger;
use App\Services\Sync\Mergers\ShotMerger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Applies a push in one transaction that holds the user's row lock, so the user's seq values
 * commit in order and a pull never skips a gap.
 */
class SyncPush
{
    public const MAX_ITEMS = 500;

    /**
     * Request key => merger, in processing order (categories before the shots that use them).
     *
     * @var array<string, class-string<Merger>>
     */
    private const MERGERS = [
        'categories' => CategoryMerger::class,
        'shots' => ShotMerger::class,
        'settings' => SettingMerger::class,
    ];

    /**
     * @param  array<string, mixed>  $input
     * @return array{accepted: array<string, list<string>>, rejected: list<array<string, mixed>>, seq: int}
     */
    public function handle(User $user, array $input): array
    {
        $batches = $this->batches($input);

        return DB::transaction(function () use ($user, $batches) {
            $start = User::whereKey($user->id)->lockForUpdate()->value('sync_seq');
            $seq = new SeqAllocator($start);

            $accepted = ['shots' => [], 'categories' => [], 'settings' => [], 'backups' => []];
            $rejected = [];

            foreach ($batches as $key => $items) {
                $merger = app(self::MERGERS[$key]);

                foreach ($items as $item) {
                    $rejection = $this->apply($merger, $user, $item, $seq);

                    if ($rejection === null) {
                        $accepted[$key][] = $merger->idOf($item);
                    } else {
                        $rejected[] = $rejection;
                    }
                }
            }

            if ($seq->current() !== $start) {
                User::whereKey($user->id)->update(['sync_seq' => $seq->current()]);
                $user->sync_seq = $seq->current();
            }

            return ['accepted' => $accepted, 'rejected' => $rejected, 'seq' => $seq->current()];
        });
    }

    /**
     * @return array<string, mixed>|null The rejection, or null when the item was processed.
     */
    private function apply(Merger $merger, User $user, mixed $item, SeqAllocator $seq): ?array
    {
        if (! is_array($item) || (array_is_list($item) && $item !== [])) {
            return ['entity' => $merger->entity(), 'id' => '', 'code' => 'validation_failed'];
        }

        $rejection = ['entity' => $merger->entity(), 'id' => $merger->idOf($item)];
        $validator = Validator::make($item, $merger->rules());

        if ($validator->fails()) {
            return $rejection + ['code' => 'validation_failed', 'fields' => $validator->errors()->toArray()];
        }

        try {
            $merger->merge($user, $item, $seq);
        } catch (RejectedItem $e) {
            return $rejection + ['code' => $e->reason] + ($e->fields === null ? [] : ['fields' => $e->fields]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, list<mixed>>
     */
    private function batches(array $input): array
    {
        $batches = [];

        foreach (array_keys(self::MERGERS) as $key) {
            $items = $input[$key] ?? [];

            if (! is_array($items) || ! array_is_list($items)) {
                throw new ApiException(422, 'validation_failed', 'The given data was invalid.', [$key => ["The {$key} field must be an array."]]);
            }

            $batches[$key] = $items;
        }

        if (array_sum(array_map('count', $batches)) > self::MAX_ITEMS) {
            throw new ApiException(422, 'too_many_items', 'A push may contain at most '.self::MAX_ITEMS.' items.');
        }

        return $batches;
    }
}
