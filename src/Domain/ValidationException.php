<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** Ошибки проверки входных данных: поле → сообщение. */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Данные не прошли проверку.');
    }
}
