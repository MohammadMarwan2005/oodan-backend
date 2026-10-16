<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Wire timestamps: ISO-8601 UTC with milliseconds, e.g. `2026-10-16T20:00:00.000Z`.
 */
class Timestamp
{
    /**
     * Accepts ISO-8601 with any offset and up to microsecond precision.
     */
    public const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:?\d{2})$/';

    public static function format(?DateTimeInterface $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Parse a wire timestamp into UTC, truncated to milliseconds (the database precision).
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $parsed = CarbonImmutable::parse($value)->utc();

        return $parsed->setTime($parsed->hour, $parsed->minute, $parsed->second, intdiv($parsed->micro, 1000) * 1000);
    }
}
