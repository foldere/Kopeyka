<?php

declare(strict_types=1);

namespace App\Domain;

interface TransactionRepositoryInterface
{
    public function find(int $id): ?Transaction;

    /** Сохраняет новую транзакцию и возвращает её уже с id. */
    public function add(Transaction $transaction): Transaction;

    public function update(Transaction $transaction): void;

    /** @return bool true, если запись существовала и удалена */
    public function delete(int $id): bool;

    /** @return list<Transaction> */
    public function search(TransactionFilter $filter): array;

    public function count(TransactionFilter $filter): int;
}
