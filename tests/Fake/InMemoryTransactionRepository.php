<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Domain\Transaction;
use App\Domain\TransactionFilter;
use App\Domain\TransactionRepositoryInterface;

final class InMemoryTransactionRepository implements TransactionRepositoryInterface
{
    /** @var array<int, Transaction> */
    private array $items = [];
    private int $nextId = 1;

    public function find(int $id): ?Transaction
    {
        return $this->items[$id] ?? null;
    }

    public function add(Transaction $transaction): Transaction
    {
        $saved = $transaction->withId($this->nextId++);
        $this->items[$saved->id] = $saved;

        return $saved;
    }

    public function update(Transaction $transaction): void
    {
        $this->items[$transaction->id] = $transaction;
    }

    public function delete(int $id): bool
    {
        $exists = isset($this->items[$id]);
        unset($this->items[$id]);

        return $exists;
    }

    public function search(TransactionFilter $filter): array
    {
        $items = $this->filtered($filter);
        usort($items, static fn (Transaction $a, Transaction $b): int => [$b->occurredOn, $b->id] <=> [$a->occurredOn, $a->id]);

        return array_slice($items, $filter->offset(), $filter->perPage);
    }

    public function count(TransactionFilter $filter): int
    {
        return count($this->filtered($filter));
    }

    /** @return list<Transaction> */
    private function filtered(TransactionFilter $f): array
    {
        return array_values(array_filter($this->items, static fn (Transaction $t): bool =>
            ($f->from === null || $t->occurredOn >= $f->from)
            && ($f->to === null || $t->occurredOn <= $f->to)
            && ($f->categoryId === null || $t->categoryId === $f->categoryId)
            && ($f->type === null || $t->type === $f->type)
        ));
    }
}
