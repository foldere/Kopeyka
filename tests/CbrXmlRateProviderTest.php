<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\RateUnavailableException;
use App\Infrastructure\Http\HttpException;
use App\Infrastructure\Rates\CbrXmlRateProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeHttpClient;

final class CbrXmlRateProviderTest extends TestCase
{
    public function testParsesWindows1251XmlAndKeepsOnlySupportedCurrencies(): void
    {
        $http = new FakeHttpClient(static fn (string $url): string => file_get_contents(__DIR__ . '/fixtures/cbr_daily.xml'));
        $provider = new CbrXmlRateProvider($http, 'https://cbr.test');

        $rates = $provider->ratesOn(new DateTimeImmutable('2026-10-14'));

        $this->assertSame(95_123_400, $rates['USD']);
        $this->assertSame(103_567_800, $rates['EUR']);
        $this->assertSame(190_015, $rates['KZT'], 'тенге указаны за 100 штук');
        $this->assertArrayNotHasKey('JPY', $rates, 'иена не входит в поддерживаемые валюты');
        $this->assertSame(['https://cbr.test/scripts/XML_daily.asp?date_req=14/10/2026'], $http->requested);
    }

    public function testBrokenXmlBecomesRateUnavailable(): void
    {
        $provider = new CbrXmlRateProvider(new FakeHttpClient(static fn (): string => '<html>not xml'));

        $this->expectException(RateUnavailableException::class);
        $provider->ratesOn(new DateTimeImmutable('2026-10-14'));
    }

    public function testNetworkErrorBecomesRateUnavailable(): void
    {
        $provider = new CbrXmlRateProvider(new FakeHttpClient(static function (): string {
            throw new HttpException('timeout');
        }));

        $this->expectException(RateUnavailableException::class);
        $provider->ratesOn(new DateTimeImmutable('2026-10-14'));
    }
}
