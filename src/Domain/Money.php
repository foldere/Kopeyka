<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;
use OverflowException;

/**
 * Деньги = целое число минимальных единиц (копеек, центов) + валюта.
 * Никакого float: 0.1 + 0.2 !== 0.3, а в финансах так нельзя.
 */
final class Money
{
    private function __construct(
        public readonly int $minor,
        public readonly Currency $currency,
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Сумма не может быть отрицательной.');
        }
    }

    public static function ofMinor(int $minor, Currency $currency): self
    {
        return new self($minor, $currency);
    }

    /** Разбирает «123», «123.4», «123.45». До 7 цифр до точки — это защищает от переполнения при конвертации. */
    public static function fromDecimalString(string $value, Currency $currency): self
    {
        if (preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $value, $m) !== 1) {
            throw new InvalidArgumentException('Сумма должна быть числом вида 123 или 123.45 (до 7 цифр до точки).');
        }

        $minor = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
        if ($minor === 0) {
            throw new InvalidArgumentException('Сумма должна быть больше нуля.');
        }

        return new self($minor, $currency);
    }

    /** Форматирует целое число минимальных единиц как «123.45» (допускает минус для баланса). */
    public static function format(int $minor): string
    {
        $abs = abs($minor);

        return ($minor < 0 ? '-' : '') . sprintf('%d.%02d', intdiv($abs, 100), $abs % 100);
    }

    public function toDecimalString(): string
    {
        return self::format($this->minor);
    }

    /**
     * @param int $rubPerUnitMicro сколько рублей стоит 1 единица валюты, умноженное на 1 000 000
     */
    public function convertToRub(int $rubPerUnitMicro): self
    {
        $product = $this->minor * $rubPerUnitMicro;
        if (!is_int($product)) { // при переполнении PHP молча превращает int в float
            throw new OverflowException('Слишком большая сумма для конвертации.');
        }

        // округление «half up»: прибавляем половину делителя перед целочисленным делением
        return new self(intdiv($product + 500_000, 1_000_000), Currency::RUB);
    }
}
