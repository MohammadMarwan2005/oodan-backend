<?php

namespace App\Services\Sync\Mergers;

use App\Models\Backup;
use App\Models\User;
use App\Services\Sync\RejectedItem;
use App\Services\Sync\SeqAllocator;
use App\Support\Timestamp;

/**
 * New id → insert (`payload` required). Existing id → only `deleted_at` can change, once;
 * deleting a backup drops its payload.
 */
class BackupMerger implements Merger
{
    public const MAX_PAYLOAD_BYTES = 10 * 1024 * 1024;

    public function entity(): string
    {
        return 'backup';
    }

    public function idOf(array $item): string
    {
        return is_string($item['id'] ?? null) ? strtolower($item['id']) : '';
    }

    public function rules(): array
    {
        return [
            'id' => Rules::uuid(),
            'reason' => ['required', 'in:clear,import_replace,restore'],
            'shots_count' => ['required', 'integer', 'between:0,4294967295'],
            'created_at' => Rules::timestamp(),
            'deleted_at' => Rules::timestamp(nullable: true),
            'payload' => ['sometimes', 'array'],
            'payload.format' => ['required_with:payload', 'in:oodan-export'],
            'payload.version' => ['required_with:payload', 'integer', 'in:1'],
            'payload.exported_at' => ['required_with:payload', 'string'],
            'payload.shots' => ['present_with:payload', 'array', 'list'],
            'payload.categories' => ['present_with:payload', 'array', 'list'],
            'payload.settings' => ['present_with:payload', 'array'],
        ];
    }

    public function merge(User $user, array $item, SeqAllocator $seq): void
    {
        $id = $this->idOf($item);
        $backup = Backup::find($id);

        if ($backup !== null && (int) $backup->user_id !== $user->id) {
            throw new RejectedItem('id_conflict');
        }

        $deletedAt = Timestamp::parse($item['deleted_at']);

        if ($backup === null) {
            if (! isset($item['payload']) && $deletedAt === null) {
                throw new RejectedItem('payload_required');
            }

            $payload = $deletedAt === null ? $this->encode($item['payload']) : null;

            Backup::create([
                'id' => $id,
                'user_id' => $user->id,
                'reason' => $item['reason'],
                'shots_count' => (int) $item['shots_count'],
                'payload' => $payload,
                'created_at' => Timestamp::parse($item['created_at']),
                'deleted_at' => $deletedAt,
                'seq' => $seq->next(),
            ]);

            return;
        }

        if ($backup->deleted_at === null && $deletedAt !== null) {
            $backup->fill(['deleted_at' => $deletedAt, 'payload' => null, 'seq' => $seq->next()])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws RejectedItem
     */
    private function encode(array $payload): string
    {
        // `settings` is a JSON object even when empty; PHP decodes `{}` to [].
        $payload['settings'] = (object) $payload['settings'];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new RejectedItem('payload_too_large');
        }

        return $json;
    }
}
