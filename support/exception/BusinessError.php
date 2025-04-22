<?php declare(strict_types=1);

namespace support\exception;

use RuntimeException;

/**
 * 由业务抛出的异常
 */
class BusinessError extends RuntimeException
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }
}