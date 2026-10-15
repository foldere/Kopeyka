<?php

declare(strict_types=1);

namespace Tests;

use App\Application\CurrencyConverter;
use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\RateUnavailableException;
use App\Infrastructure\Rates\StaticRateProvider;
use App\Application\RateProviderInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CurrencyConverterTest extends TestCase
{
    private DateTimeImmutable $day;

    protected function setUp(): void
    {
        $this->day = new DateTimeImmutable('2026-10-14');
    }

    public function testRublesAreNotConverted(): void
    {
        $result = (new CurrencyConverter(new StaticRateProvider()))->toRub(Money::ofMinor(5000, Currency::RUB), $this->day);

        $this->assertSame(1_000_000, $result->rateMicro);
        $this->assertSame(5000, $result->rub->minor);
    }

    public function testForeignCurrencyUsesProviderRate(): void
    {
        // статичный курс: 95 ₽ за доллар → 10.00 USD = 950.00 ₽
        $result = (new CurrencyConverter(new StaticRateProvider()))->toRub(Money::ofMinor(1000, Currency::USD), $this->day);

        $this->assertSame(95_000_000, $result->rateMicro);
        $this->assertSame(95000, $result->rub->minor);
    }

    public function testMissingRateThrows(): void
    {
        $empty = new class implements RateProviderInterface {
            public function ratesOn(DateTimeImmutable $date): array
            {
                return [];
            }
        };

        $this->expectException(RateUnavailableException::class);
        (new CurrencyConverter($empty))->toRub(Money::ofMinor(1000, Currency::EUR), $this->day);
    }
}
