<?php

namespace App\Http\Resources;

use App\Models\UserSetting;
use App\Support\Timestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserSetting
 */
class SettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'changed_at' => Timestamp::format($this->changed_at),
            'seq' => $this->seq,
        ];
    }
}
