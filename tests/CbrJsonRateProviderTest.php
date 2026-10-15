<?php

declare(strict_types=1);

namespace Tests;

use App\Domain\RateUnavailableException;
use App\Infrastructure\Http\HttpException;
use App\Infrastructure\Rates\CbrJsonRateProvider;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeHttpClient;

final class CbrJsonRateProviderTest extends TestCase
{
    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/cbr_daily.json');
    }

    public function testParsesJson(): void
    {
        $provider = new CbrJsonRateProvider(new FakeHttpClient(fn (): string => $this->fixture()), 'https://x.test');

        $rates = $provider->ratesOn(new DateTimeImmutable('2026-10-14'));

        $this->assertSame(['USD', 'EUR', 'KZT'], array_keys($rates));
        $this->assertSame(95_123_400, $rates['USD']);
        $this->assertSame(190_015, $rates['KZT']);
    }

    public function testFallsBackToPreviousDayWhenArchiveIsMissing(): void
    {
        // 2026-10-17 — суббота: архива за выходные нет (404), поэтому ждём курс пятницы
        $http = new FakeHttpClient(function (string $url): string {
            if (str_contains($url, '/2026/10/16/')) {
                return $this->fixture();
            }
            throw new HttpException('нет архива', 404);
        });

        $rates = (new CbrJsonRateProvider($http, 'https://x.test'))->ratesOn(new DateTimeImmutable('2026-10-18'));

        $this->assertSame(95_123_400, $rates['USD']);
        $this->assertCount(3, $http->requested);
        $this->assertStringContainsString('/2026/10/18/', $http->requested[0]);
    }

    public function testServerErrorIsNotRetried(): void
    {
        $http = new FakeHttpClient(static function (): string {
            throw new HttpException('ошибка сервера', 500);
        });

        try {
            (new CbrJsonRateProvider($http))->ratesOn(new DateTimeImmutable('2026-10-14'));
            $this->fail('Ожидалась RateUnavailableException');
        } catch (RateUnavailableException) {
            $this->assertCount(1, $http->requested);
        }
    }

    public function testGivesUpAfterAWeek(): void
    {
        $http = new FakeHttpClient(static function (): string {
            throw new HttpException('нет архива', 404);
        });

        $this->expectException(RateUnavailableException::class);
        (new CbrJsonRateProvider($http))->ratesOn(new DateTimeImmutable('2026-10-14'));
    }
}
