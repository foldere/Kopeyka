<?php

declare(strict_types=1);

namespace App\Infrastructure\Rates;

use App\Application\RateProviderInterface;
use DateTimeImmutable;

/** Фиксированные демо-курсы: приложение работает без интернета, тесты не зависят от сети. */
final class StaticRateProvider implements RateProviderInterface
{
    public function ratesOn(DateTimeImmutable $date): array
    {
        return [
            'USD' => 95_000_000,
            'EUR' => 103_000_000,
            'CNY' => 13_100_000,
            'GBP' => 120_000_000,
            'KZT' => 190_000,
        ];
    }
}
