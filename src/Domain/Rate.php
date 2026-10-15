<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Курс хранится как целое число: «рублей за 1 единицу валюты» × 1 000 000.
 * Пример: 95,1234 ₽ за 1 USD → 95 123 400.
 */
final class Rate
{
    /**
     * @param string|int|float $value курс как в источнике (ЦБ отдаёт «95,1234»)
     * @param int $nominal за сколько единиц валюты указан курс (у тенге — за 100)
     */
    public static function parseMicro(string|int|float $value, int $nominal = 1): int
    {
        if ($nominal < 1) {
            throw new InvalidArgumentException('Номинал должен быть положительным.');
        }

        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $text, $m) !== 1) {
            throw new InvalidArgumentException("Некорректный курс: «{$value}».");
        }

        $micro = (int) $m[1] * 1_000_000 + (int) str_pad(substr($m[2] ?? '', 0, 6), 6, '0');

        return intdiv($micro * 2 + $nominal, $nominal * 2); // деление с округлением
    }

    public static function format(int $micro): string
    {
        return sprintf('%d.%06d', intdiv($micro, 1_000_000), $micro % 1_000_000);
    }
}
