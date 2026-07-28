<?php

namespace App\Exceptions;

use RuntimeException;

class GateScanException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
