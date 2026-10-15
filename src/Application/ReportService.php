<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\DateRange;
use App\Domain\Money;
use App\Domain\ReportRepositoryInterface;
use App\Domain\TransactionType;

final class ReportService
{
    public function __construct(private readonly ReportRepositoryInterface $reports)
    {
    }

    /** @return list<array<string, string>> */
    public function monthly(DateRange $range): array
    {
        return array_map(static fn (array $row): array => [
            'month' => $row['month'],
            'income' => Money::format($row['income']),
            'expense' => Money::format($row['expense']),
            'balance' => Money::format($row['income'] - $row['expense']),
        ], $this->reports->monthly($range->from, $range->to));
    }

    /** @return list<array<string, mixed>> */
    public function byCategory(TransactionType $type, DateRange $range): array
    {
        return array_map(static fn (array $row): array => [
            'category_id' => $row['category_id'],
            'category' => $row['category'],
            'total' => Money::format($row['total']),
            'count' => $row['count'],
        ], $this->reports->byCategory($type, $range->from, $range->to));
    }
}
