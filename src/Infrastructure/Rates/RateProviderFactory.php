<?php

declare(strict_types=1);

namespace App\Infrastructure\Rates;

use App\Application\RateProviderInterface;
use App\Infrastructure\Http\HttpClientInterface;
use InvalidArgumentException;

/** Паттерн «Фабрика»: выбирает реализацию по имени из настроек. */
final class RateProviderFactory
{
    public static function create(string $kind, HttpClientInterface $http): RateProviderInterface
    {
        return match ($kind) {
            'static' => new StaticRateProvider(),
            'cbr_xml' => new CbrXmlRateProvider($http),
            'cbr_json' => new CbrJsonRateProvider($http),
            default => throw new InvalidArgumentException("Неизвестный RATE_PROVIDER: «{$kind}». Допустимо: static, cbr_xml, cbr_json."),
        };
    }
}
