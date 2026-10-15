<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Support\Clock;
use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private readonly string $date = '2026-10-14')
    {
    }

    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->date);
    }
}
