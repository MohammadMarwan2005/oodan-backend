<?php

namespace App\Services\Sync;

use App\Models\Backup;
use App\Models\Category;
use App\Models\Shot;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Returns one page of a user's changes with `seq > since`, merged across entities in seq order.
 */
class SyncPull
{
    /**
     * Response key => model, for every synced entity.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'shots' => Shot::class,
        'categories' => Category::class,
        'settings' => UserSetting::class,
        'backups' => Backup::class,
    ];

    /**
     * Pulls carry backup metadata only; the payload is fetched with GET /backups/{id}.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'backups' => Backup::META_COLUMNS,
    ];

    /**
     * @return array{items: array<string, Collection<int, Model>>, next_since: int, has_more: bool}
     */
    public function handle(User $user, int $since, int $limit): array
    {
        // Fetch limit + 1 per entity: if the merged rows exceed the limit, there is more to pull.
        $rows = collect(self::MODELS)
            ->flatMap(fn (string $model, string $key) => $model::query()
                ->select(self::COLUMNS[$key] ?? ['*'])
                ->where('user_id', $user->id)
                ->where('seq', '>', $since)
                ->orderBy('seq')
                ->limit($limit + 1)
                ->get()
                ->map(fn (Model $row) => [$key, $row]))
            ->sortBy(fn (array $entry) => $entry[1]->seq)
            ->values();

        $page = $rows->take($limit);

        $items = collect(self::MODELS)->map(fn () => collect())->all();

        foreach ($page as [$key, $row]) {
            $items[$key]->push($row);
        }

        return [
            'items' => $items,
            'next_since' => $page->isEmpty() ? $since : $page->last()[1]->seq,
            'has_more' => $rows->count() > $limit,
        ];
    }
}
