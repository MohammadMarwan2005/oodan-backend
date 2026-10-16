<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An API error rendered with the shared `{ "error": { code, message, fields? } }` shape.
 */
class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $fields
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly ?array $fields = null,
    ) {
        parent::__construct($message);
    }
}
