<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Domain\ReportRepositoryInterface;
use App\Domain\TransactionType;
use DateTimeImmutable;

/** Отчётные SQL-запросы проверяются на настоящей БД; в юнит-тестах достаточно пустой заглушки. */
final class EmptyReportRepository implements ReportRepositoryInterface
{
    public function monthly(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }

    public function byCategory(TransactionType $type, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [];
    }
}
