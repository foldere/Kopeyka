<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Application\RateCacheInterface;
use DateTimeImmutable;

final class InMemoryRateCache implements RateCacheInterface
{
    /** @var array<string, array<string, int>> */
    private array $data = [];

    public function find(DateTimeImmutable $date): array
    {
        return $this->data[$date->format('Y-m-d')] ?? [];
    }

    public function store(DateTimeImmutable $date, array $rates): void
    {
        $this->data[$date->format('Y-m-d')] = $rates;
    }
}
