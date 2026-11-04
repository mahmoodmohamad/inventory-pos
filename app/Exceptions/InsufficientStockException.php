<?php

// app/Exceptions/InsufficientStockException.php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function for(int $productId, float $available, float $requested): self
    {
        return new self(sprintf(
            'Insufficient stock for product #%d: available %s, requested %s.',
            $productId,
            $available,
            $requested,
        ));
    }
}
