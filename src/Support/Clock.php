<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

/** Время — это зависимость: так его легко подменить в тестах. */
interface Clock
{
    public function today(): DateTimeImmutable;
}
