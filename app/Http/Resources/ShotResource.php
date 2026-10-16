<?php

namespace App\Http\Resources;

use App\Models\Shot;
use App\Support\Timestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Shot
 */
class ShotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'color' => $this->color,
            'x' => $this->x,
            'y' => $this->y,
            'r' => $this->r,
            'intensity' => $this->intensity,
            'shot_at' => Timestamp::format($this->shot_at),
            'undone_at' => Timestamp::format($this->undone_at),
            'deleted_at' => Timestamp::format($this->deleted_at),
            'state_changed_at' => Timestamp::format($this->state_changed_at),
            'note' => $this->note,
            'category_id' => $this->category_id,
            'meta_changed_at' => Timestamp::format($this->meta_changed_at),
            'device_id' => $this->device_id,
            'seq' => $this->seq,
        ];
    }
}
