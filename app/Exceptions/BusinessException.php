<?php

namespace App\Exceptions;

use InvalidArgumentException;
use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 409,
        public readonly ?string $errorCode = null,
    ) {
        if ($httpStatus < 400 || $httpStatus > 599) {
            throw new InvalidArgumentException('A business exception must use an error HTTP status.');
        }

        parent::__construct($message);
    }
}
