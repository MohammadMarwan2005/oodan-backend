<?php

namespace App\Http\Resources;

use App\Models\Backup;
use App\Support\Timestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Backup
 */
class BackupMetaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'shots_count' => $this->shots_count,
            'created_at' => Timestamp::format($this->created_at),
            'deleted_at' => Timestamp::format($this->deleted_at),
            'seq' => $this->seq,
        ];
    }
}
