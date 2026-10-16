<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A synced category. `deleted_at` is a synced soft delete (not Eloquent's SoftDeletes).
 */
class Category extends Model
{
    public $incrementing = false;

    public $timestamps = false;

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
            'sort' => 'integer',
            'changed_at' => 'immutable_datetime',
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
