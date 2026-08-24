<?php

namespace Dagemawi\RelayHub\Exceptions;

use RuntimeException;

final class IdempotencyConflict extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("Idempotency key '{$key}' was already used for different request content.");
    }
}
