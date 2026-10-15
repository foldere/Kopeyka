<?php

declare(strict_types=1);

namespace Tests;

use App\Application\RateProviderInterface;
use App\Infrastructure\Rates\CachingRateProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fake\InMemoryRateCache;

final class CachingRateProviderTest extends TestCase
{
    public function testAsksSourceOnlyOncePerDate(): void
    {
        $inner = new class implements RateProviderInterface {
            public int $calls = 0;

            public function ratesOn(DateTimeImmutable $date): array
            {
                $this->calls++;

                return ['USD' => 95_000_000];
            }
        };
        $provider = new CachingRateProvider($inner, new InMemoryRateCache());

        $day = new DateTimeImmutable('2026-10-14');
        $provider->ratesOn($day);
        $second = $provider->ratesOn($day);
        $provider->ratesOn(new DateTimeImmutable('2026-10-13'));

        $this->assertSame(['USD' => 95_000_000], $second);
        $this->assertSame(2, $inner->calls, 'один раз на каждую дату');
    }
}
