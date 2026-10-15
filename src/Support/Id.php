<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\NotFoundException;

final class Id
{
    /** id из адреса: только положительное целое, иначе — 404. */
    public static function parse(string $value): int
    {
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new NotFoundException('Ресурс не найден.');
        }

        return (int) $value;
    }
}
