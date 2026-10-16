<?php

namespace App\Services\Sync\Mergers;

use App\Support\Timestamp;

/**
 * Validation rule fragments shared by the mergers.
 */
class Rules
{
    /**
     * @return list<string>
     */
    public static function timestamp(bool $nullable = false): array
    {
        return [$nullable ? 'present' : 'required', ...($nullable ? ['nullable'] : []), 'string', 'regex:'.Timestamp::PATTERN, 'date'];
    }

    /**
     * @return list<string>
     */
    public static function uuid(bool $nullable = false): array
    {
        return [$nullable ? 'present' : 'required', ...($nullable ? ['nullable'] : []), 'string', 'uuid'];
    }
}
