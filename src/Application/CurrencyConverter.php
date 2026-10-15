<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\RateUnavailableException;
use DateTimeImmutable;

final class CurrencyConverter
{
    public function __construct(private readonly RateProviderInterface $rates)
    {
    }

    public function toRub(Money $amount, DateTimeImmutable $date): Conversion
    {
        if ($amount->currency === Currency::RUB) {
            return new Conversion(1_000_000, $amount);
        }

        $code = $amount->currency->value;
        $rate = $this->rates->ratesOn($date)[$code]
            ?? throw new RateUnavailableException("Нет курса {$code} на {$date->format('Y-m-d')}.");

        return new Conversion($rate, $amount->convertToRub($rate));
    }

    /** @return array<string, int> */
    public function ratesOn(DateTimeImmutable $date): array
    {
        return $this->rates->ratesOn($date);
    }
}
