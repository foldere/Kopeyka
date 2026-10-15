<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class Dates
{
    /** Строгий разбор даты ГГГГ-ММ-ДД. Возвращает null, если формат неверный или даты не существует (2026-02-31). */
    public static function parse(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
}
