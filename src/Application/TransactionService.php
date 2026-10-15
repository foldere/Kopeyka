<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\CategoryRepositoryInterface;
use App\Domain\NotFoundException;
use App\Domain\Transaction;
use App\Domain\TransactionFilter;
use App\Domain\TransactionRepositoryInterface;
use App\Domain\ValidationException;

/** Сценарии работы с транзакциями. Знает правила, но не знает ни про HTTP, ни про SQL. */
final class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly CategoryRepositoryInterface $categories,
        private readonly CurrencyConverter $converter,
    ) {
    }

    public function create(TransactionInput $input): Transaction
    {
        return $this->transactions->add($this->build(null, $input));
    }

    public function update(int $id, TransactionInput $input): Transaction
    {
        $this->get($id);
        $transaction = $this->build($id, $input);
        $this->transactions->update($transaction);

        return $transaction;
    }

    public function get(int $id): Transaction
    {
        return $this->transactions->find($id)
            ?? throw new NotFoundException('Транзакция не найдена.');
    }

    public function delete(int $id): void
    {
        if (!$this->transactions->delete($id)) {
            throw new NotFoundException('Транзакция не найдена.');
        }
    }

    /** @return array{items: list<Transaction>, total: int} */
    public function list(TransactionFilter $filter): array
    {
        return [
            'items' => $this->transactions->search($filter),
            'total' => $this->transactions->count($filter),
        ];
    }

    private function build(?int $id, TransactionInput $input): Transaction
    {
        if ($this->categories->find($input->categoryId) === null) {
            throw new ValidationException(['category_id' => 'Категория не найдена.']);
        }

        // Курс фиксируется в момент создания: отчёты потом не «плывут» при смене курса.
        $conversion = $this->converter->toRub($input->amount, $input->occurredOn);

        return new Transaction(
            $id,
            $input->categoryId,
            $input->type,
            $input->amount,
            $conversion->rateMicro,
            $conversion->rub,
            $input->occurredOn,
            $input->note,
        );
    }
}
