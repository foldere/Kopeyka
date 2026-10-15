<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

interface ReportRepositoryInterface
{
    /**
     * Доходы и расходы по месяцам, в копейках.
     *
     * @return list<array{month: string, income: int, expense: int}>
     */
    public function monthly(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * Суммы по категориям, в копейках, от большей к меньшей.
     *
     * @return list<array{category_id: int, category: string, total: int, count: int}>
     */
    public function byCategory(TransactionType $type, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
