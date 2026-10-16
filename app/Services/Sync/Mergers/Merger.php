<?php

namespace App\Services\Sync\Mergers;

use App\Models\User;
use App\Services\Sync\RejectedItem;
use App\Services\Sync\SeqAllocator;

/**
 * Applies one pushed item of an entity type with last-write-wins rules.
 */
interface Merger
{
    /**
     * Singular entity name used in rejections, e.g. `shot`.
     */
    public function entity(): string;

    /**
     * The item's identity as reported back to the client (an id, or the key for settings).
     *
     * @param  array<string, mixed>  $item
     */
    public function idOf(array $item): string;

    /**
     * Validation rules for a single item.
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * Insert or merge the (validated) item. Allocates a seq only when a row actually changes.
     *
     * @param  array<string, mixed>  $item
     *
     * @throws RejectedItem
     */
    public function merge(User $user, array $item, SeqAllocator $seq): void;
}
