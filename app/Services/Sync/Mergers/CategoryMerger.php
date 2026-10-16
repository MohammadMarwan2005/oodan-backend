<?php

namespace App\Services\Sync\Mergers;

use App\Models\Category;
use App\Models\User;
use App\Services\Sync\RejectedItem;
use App\Services\Sync\SeqAllocator;
use App\Support\Timestamp;

/**
 * New id → insert. Existing id → every mutable field is replaced when `changed_at` is newer;
 * `kind` is immutable. Ties keep the server value.
 */
class CategoryMerger implements Merger
{
    public function entity(): string
    {
        return 'category';
    }

    public function idOf(array $item): string
    {
        return is_string($item['id'] ?? null) ? strtolower($item['id']) : '';
    }

    public function rules(): array
    {
        return [
            'id' => Rules::uuid(),
            'kind' => ['required', 'in:white,black'],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['present', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['present', 'nullable', 'string', 'max:40'],
            'preset_key' => ['present', 'nullable', 'string', 'max:40'],
            'sort' => ['required', 'integer', 'between:-2147483648,2147483647'],
            'changed_at' => Rules::timestamp(),
            'deleted_at' => Rules::timestamp(nullable: true),
        ];
    }

    public function merge(User $user, array $item, SeqAllocator $seq): void
    {
        $id = $this->idOf($item);
        $category = Category::find($id);

        if ($category !== null && (int) $category->user_id !== $user->id) {
            throw new RejectedItem('id_conflict');
        }

        $mutable = [
            'name' => $item['name'],
            'color' => $item['color'],
            'icon' => $item['icon'],
            'preset_key' => $item['preset_key'],
            'sort' => (int) $item['sort'],
            'changed_at' => Timestamp::parse($item['changed_at']),
            'deleted_at' => Timestamp::parse($item['deleted_at']),
        ];

        if ($category === null) {
            Category::create(['id' => $id, 'user_id' => $user->id, 'kind' => $item['kind'], 'seq' => $seq->next()] + $mutable);

            return;
        }

        if ($mutable['changed_at']->gt($category->changed_at)) {
            $category->fill($mutable + ['seq' => $seq->next()])->save();
        }
    }
}
