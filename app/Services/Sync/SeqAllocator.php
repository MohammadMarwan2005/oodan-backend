<?php

namespace App\Services\Sync;

/**
 * Hands out a user's next sync sequence numbers inside a push transaction.
 */
class SeqAllocator
{
    public function __construct(private int $current) {}

    public function next(): int
    {
        return ++$this->current;
    }

    public function current(): int
    {
        return $this->current;
    }
}
