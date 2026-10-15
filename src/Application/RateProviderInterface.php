<?php

declare(strict_types=1);

namespace App\Application;

use DateTimeImmutable;

/**
 * Источник курсов валют. Остальной код не знает, откуда курсы берутся:
 * из ЦБ в XML, из JSON или из заглушки. Это принцип инверсии зависимостей (D из SOLID).
 */
interface RateProviderInterface
{
    /**
     * Курсы всех поддерживаемых валют на дату: код валюты → «рублей за 1 единицу» × 1 000 000.
     *
     * @return array<string, int>
     * @throws \App\Domain\RateUnavailableException
     */
    public function ratesOn(DateTimeImmutable $date): array;
}
