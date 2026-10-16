<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Timestamp;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->hasVerifiedEmail(),
            'has_password' => $this->password !== null,
            'google_linked' => $this->google_sub !== null,
            'created_at' => Timestamp::format($this->created_at),
        ];
    }
}
