<?php

namespace App\Exceptions;

use Exception;

abstract class BaseException extends Exception
{
    public function __construct(string $message, protected int $httpCode = 400)
    {
        parent::__construct($message);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
