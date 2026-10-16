<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A synced key–value setting; the primary key is (user_id, key).
 */
class UserSetting extends Model
{
    public const KEYS = ['dot_size', 'intensity', 'sound_enabled'];

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'changed_at' => 'immutable_datetime',
            'seq' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope updates and deletes to this user's row, not every row with the same key.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        return $query->where('user_id', $this->getAttribute('user_id'))->where('key', $this->getAttribute('key'));
    }
}
