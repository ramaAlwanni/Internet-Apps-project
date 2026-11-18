<?php

namespace App\Exceptions;

use Throwable;

/**
 * Raised when the inventory (warehouse) service is down, unreachable or
 * does not answer within its configured deadline.
 */
class InventoryUnavailableException extends BaseException
{
    public const DEFAULT_MESSAGE =
        'Inventory service is temporarily unavailable. Please try again later.';

    public function __construct(
        string $message = self::DEFAULT_MESSAGE,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 503, $previous);
    }
}
