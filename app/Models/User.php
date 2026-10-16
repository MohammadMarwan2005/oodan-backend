<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'google_sub', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'google_sub'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sync_seq' => 'integer',
            'is_admin' => 'boolean',
        ];
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<Shot, $this>
     */
    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<UserSetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * @return HasMany<AuthCode, $this>
     */
    public function authCodes(): HasMany
    {
        return $this->hasMany(AuthCode::class);
    }
}
