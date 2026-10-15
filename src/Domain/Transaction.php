<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class Transaction
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $categoryId,
        public readonly TransactionType $type,
        public readonly Money $amount,
        public readonly int $rateMicro,
        public readonly Money $amountRub,
        public readonly DateTimeImmutable $occurredOn,
        public readonly string $note,
    ) {
    }

    public function withId(int $id): self
    {
        return new self(
            $id, $this->categoryId, $this->type, $this->amount,
            $this->rateMicro, $this->amountRub, $this->occurredOn, $this->note
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->categoryId,
            'type' => $this->type->value,
            'amount' => $this->amount->toDecimalString(),
            'currency' => $this->amount->currency->value,
            'rate' => Rate::format($this->rateMicro),
            'amount_rub' => $this->amountRub->toDecimalString(),
            'occurred_on' => $this->occurredOn->format('Y-m-d'),
            'note' => $this->note,
        ];
    }
}
