<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0)
    {
        parent::__construct($message);
    }
}
