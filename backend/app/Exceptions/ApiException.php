<?php

namespace App\Exceptions;

use App\Enums\ErrorCode;
use RuntimeException;
use Throwable;

class ApiException extends RuntimeException
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly int $status,
        string $message,
        public readonly ?array $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
