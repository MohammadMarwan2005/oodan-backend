<?php

namespace App\Services\Sync;

use Exception;

/**
 * Thrown by a merger to reject a single pushed item.
 */
class RejectedItem extends Exception
{
    /**
     * @param  array<string, list<string>>|null  $fields
     */
    public function __construct(public readonly string $reason, public readonly ?array $fields = null)
    {
        parent::__construct($reason);
    }
}
