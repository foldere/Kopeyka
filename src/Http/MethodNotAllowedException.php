<?php

declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    /** @param list<string> $allowed */
    public function __construct(public readonly array $allowed)
    {
        parent::__construct('Метод не поддерживается.');
    }
}
