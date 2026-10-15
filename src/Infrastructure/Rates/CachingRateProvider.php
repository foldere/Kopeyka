<?php

declare(strict_types=1);

namespace App\Infrastructure\Rates;

use App\Application\RateCacheInterface;
use App\Application\RateProviderInterface;
use DateTimeImmutable;

/**
 * Паттерн «Декоратор»: оборачивает любой провайдер и добавляет кэш,
 * не меняя ни его код, ни код тех, кто им пользуется.
 */
final class CachingRateProvider implements RateProviderInterface
{
    public function __construct(
        private readonly RateProviderInterface $inner,
        private readonly RateCacheInterface $cache,
    ) {
    }

    public function ratesOn(DateTimeImmutable $date): array
    {
        $cached = $this->cache->find($date);
        if ($cached !== []) {
            return $cached;
        }

        $rates = $this->inner->ratesOn($date);
        if ($rates !== []) {
            $this->cache->store($date, $rates);
        }

        return $rates;
    }
}
