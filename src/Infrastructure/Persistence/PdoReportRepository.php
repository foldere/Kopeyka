<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\ReportRepositoryInterface;
use App\Domain\TransactionType;
use DateTimeImmutable;
use PDO;

final class PdoReportRepository implements ReportRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function monthly(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // Условие по occurred_on написано «голым» столбцом (без функций) — тогда MySQL может использовать индекс.
        $stmt = $this->pdo->prepare(
            "SELECT DATE_FORMAT(occurred_on, '%Y-%m') AS ym,
                    SUM(CASE WHEN type = 'income'  THEN amount_rub_minor ELSE 0 END) AS income,
                    SUM(CASE WHEN type = 'expense' THEN amount_rub_minor ELSE 0 END) AS expense
             FROM transactions
             WHERE occurred_on BETWEEN :from AND :to
             GROUP BY ym
             ORDER BY ym"
        );
        $stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        return array_map(static fn (array $r): array => [
            'month' => $r['ym'],
            'income' => (int) $r['income'],
            'expense' => (int) $r['expense'],
        ], $stmt->fetchAll());
    }

    public function byCategory(TransactionType $type, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, SUM(t.amount_rub_minor) AS total, COUNT(*) AS cnt
             FROM transactions t
             JOIN categories c ON c.id = t.category_id
             WHERE t.type = :type AND t.occurred_on BETWEEN :from AND :to
             GROUP BY c.id, c.name
             ORDER BY total DESC, c.name'
        );
        $stmt->execute([
            'type' => $type->value,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return array_map(static fn (array $r): array => [
            'category_id' => (int) $r['id'],
            'category' => $r['name'],
            'total' => (int) $r['total'],
            'count' => (int) $r['cnt'],
        ], $stmt->fetchAll());
    }
}
