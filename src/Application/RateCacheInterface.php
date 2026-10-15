<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;

interface RateCacheInterface
{
    /** @return array<string, int> пустой массив, если на эту дату курсов в кэше нет */
    public function find(DateTimeImmutable $date): array;

    /** @param array<string, int> $rates */
    public function store(DateTimeImmutable $date, array $rates): void;
}
