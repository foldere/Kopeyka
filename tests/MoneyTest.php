<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Currency;
use App\Domain\Money;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testParsesDecimalStringsIntoMinorUnits(): void
    {
        $this->assertSame(1250, Money::fromDecimalString('12.5', Currency::USD)->minor);
        $this->assertSame(1205, Money::fromDecimalString('12.05', Currency::USD)->minor);
        $this->assertSame(1200, Money::fromDecimalString('12', Currency::USD)->minor);
    }

    public function testRejectsBadAmounts(): void
    {
        foreach (['0', '0.00', '-5', '1.234', 'abc', '', '12345678'] as $bad) {
            try {
                Money::fromDecimalString($bad, Currency::RUB);
                $this->fail("Сумма «{$bad}» должна быть отклонена");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFormatsBackToDecimalString(): void
    {
        $this->assertSame('12.05', Money::ofMinor(1205, Currency::RUB)->toDecimalString());
        $this->assertSame('0.07', Money::ofMinor(7, Currency::RUB)->toDecimalString());
        $this->assertSame('-3.50', Money::format(-350));
    }

    public function testConvertsToRubWithRounding(): void
    {
        // 10.00 USD по 95.1234 = 951.234 ₽ → 951.23 ₽
        $rub = Money::ofMinor(1000, Currency::USD)->convertToRub(95_123_400);

        $this->assertSame(95123, $rub->minor);
        $this->assertSame(Currency::RUB, $rub->currency);
    }

    public function testConversionOverflowIsDetected(): void
    {
        $this->expectException(OverflowException::class);
        Money::ofMinor(intdiv(PHP_INT_MAX, 2), Currency::USD)->convertToRub(3_000_000);
    }
}
