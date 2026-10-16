<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A synced shot. `deleted_at` is a synced soft delete (not Eloquent's SoftDeletes).
 */
class Shot extends Model
{
    public $incrementing = false;

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
            'x' => 'float',
            'y' => 'float',
            'r' => 'float',
            'intensity' => 'integer',
            'shot_at' => 'immutable_datetime',
            'undone_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'state_changed_at' => 'immutable_datetime',
            'meta_changed_at' => 'immutable_datetime',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
