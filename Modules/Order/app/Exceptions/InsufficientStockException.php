<?php

namespace Modules\Order\Exceptions;

use App\Exceptions\BaseException;
use Throwable;

/**
 * Raised when the requested quantity cannot be reserved because the
 * warehouse does not have enough stock.
 */
class InsufficientStockException extends BaseException
{
    public const DEFAULT_MESSAGE =
        'Insufficient stock: the requested quantity is not available. Please reduce the quantity or choose another product.';

    public function __construct(
        string $message = self::DEFAULT_MESSAGE,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 409, $previous);
    }
}
