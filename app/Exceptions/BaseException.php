<?php

namespace App\Exceptions;

use Exception;
use Throwable;

abstract class BaseException extends Exception
{
    public function __construct(
        string $message,
        protected int $httpCode = 400,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
