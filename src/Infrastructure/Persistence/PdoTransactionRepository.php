<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\Transaction;
use App\Domain\TransactionFilter;
use App\Domain\TransactionRepositoryInterface;
use App\Domain\TransactionType;
use DateTimeImmutable;
use LogicException;
use PDO;

final class PdoTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(int $id): ?Transaction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function add(Transaction $transaction): Transaction
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO transactions
                (category_id, type, amount_minor, currency, rate_micro, amount_rub_minor, occurred_on, note)
             VALUES
                (:category_id, :type, :amount_minor, :currency, :rate_micro, :amount_rub_minor, :occurred_on, :note)'
        );
        $stmt->execute($this->params($transaction));

        return $transaction->withId((int) $this->pdo->lastInsertId());
    }

    public function update(Transaction $transaction): void
    {
        if ($transaction->id === null) {
            throw new LogicException('Нельзя обновить транзакцию без id.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE transactions SET
                category_id = :category_id, type = :type, amount_minor = :amount_minor, currency = :currency,
                rate_micro = :rate_micro, amount_rub_minor = :amount_rub_minor, occurred_on = :occurred_on, note = :note
             WHERE id = :id'
        );
        $stmt->execute($this->params($transaction) + ['id' => $transaction->id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function search(TransactionFilter $filter): array
    {
        [$where, $params] = $this->where($filter);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM transactions{$where} ORDER BY occurred_on DESC, id DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('limit', $filter->perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $filter->offset(), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(fn (array $row): Transaction => $this->hydrate($row), $stmt->fetchAll());
    }

    public function count(TransactionFilter $filter): int
    {
        [$where, $params] = $this->where($filter);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM transactions{$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Условия строятся из фиксированных кусков SQL, а значения передаются параметрами —
     * поэтому SQL-инъекция невозможна.
     *
     * @return array{string, array<string, string|int>}
     */
    private function where(TransactionFilter $filter): array
    {
        $conditions = [];
        $params = [];

        if ($filter->from !== null) {
            $conditions[] = 'occurred_on >= :from';
            $params['from'] = $filter->from->format('Y-m-d');
        }
        if ($filter->to !== null) {
            $conditions[] = 'occurred_on <= :to';
            $params['to'] = $filter->to->format('Y-m-d');
        }
        if ($filter->categoryId !== null) {
            $conditions[] = 'category_id = :category_id';
            $params['category_id'] = $filter->categoryId;
        }
        if ($filter->type !== null) {
            $conditions[] = 'type = :type';
            $params['type'] = $filter->type->value;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** @return array<string, string|int> */
    private function params(Transaction $t): array
    {
        return [
            'category_id' => $t->categoryId,
            'type' => $t->type->value,
            'amount_minor' => $t->amount->minor,
            'currency' => $t->amount->currency->value,
            'rate_micro' => $t->rateMicro,
            'amount_rub_minor' => $t->amountRub->minor,
            'occurred_on' => $t->occurredOn->format('Y-m-d'),
            'note' => $t->note,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Transaction
    {
        return new Transaction(
            (int) $row['id'],
            (int) $row['category_id'],
            TransactionType::from($row['type']),
            Money::ofMinor((int) $row['amount_minor'], Currency::from($row['currency'])),
            (int) $row['rate_micro'],
            Money::ofMinor((int) $row['amount_rub_minor'], Currency::RUB),
            new DateTimeImmutable($row['occurred_on']),
            (string) $row['note'],
        );
    }
}
