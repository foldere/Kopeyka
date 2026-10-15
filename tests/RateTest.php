<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\Rate;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RateTest extends TestCase
{
    public function testParsesCommaAndDot(): void
    {
        $this->assertSame(95_123_400, Rate::parseMicro('95,1234'));
        $this->assertSame(95_123_400, Rate::parseMicro('95.1234'));
    }

    public function testNominalDividesTheRate(): void
    {
        // 19,0015 ₽ за 100 тенге = 0,190015 ₽ за 1 тенге
        $this->assertSame(190_015, Rate::parseMicro('19,0015', 100));
    }

    public function testFormat(): void
    {
        $this->assertSame('95.123400', Rate::format(95_123_400));
        $this->assertSame('0.190015', Rate::format(190_015));
    }

    public function testRejectsGarbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rate::parseMicro('abc');
    }
}
