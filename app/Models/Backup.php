<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A synced backup. `created_at` is device time; `deleted_at` is a synced soft delete.
 */
class Backup extends Model
{
    /**
     * Columns returned by a pull (everything except the payload).
     */
    public const META_COLUMNS = ['id', 'user_id', 'reason', 'shots_count', 'created_at', 'deleted_at', 'seq'];

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = [];

    protected $hidden = ['payload'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shots_count' => 'integer',
            'created_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
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
}
