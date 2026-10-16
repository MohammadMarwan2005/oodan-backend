<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Support\Timestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'name' => $this->name,
            'color' => $this->color,
            'icon' => $this->icon,
            'preset_key' => $this->preset_key,
            'sort' => $this->sort,
            'changed_at' => Timestamp::format($this->changed_at),
            'deleted_at' => Timestamp::format($this->deleted_at),
            'seq' => $this->seq,
        ];
    }
}
