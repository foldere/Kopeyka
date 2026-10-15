<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Money;

/** Результат конвертации: применённый курс и сумма в рублях. */
final class Conversion
{
    public function __construct(
        public readonly int $rateMicro,
        public readonly Money $rub,
    ) {
    }
}
