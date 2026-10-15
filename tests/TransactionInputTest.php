<?php

declare(strict_types=1);

namespace Tests;

use App\Application\TransactionInput;
use App\Domain\Currency;
use App\Domain\TransactionType;
use App\Domain\ValidationException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TransactionInputTest extends TestCase
{
    private DateTimeImmutable $today;

    protected function setUp(): void
    {
        $this->today = new DateTimeImmutable('2026-10-14');
    }

    /** @return array<string, mixed> */
    private function valid(array $override = []): array
    {
        return $override + [
            'category_id' => 1,
            'type' => 'expense',
            'amount' => '1250.50',
            'currency' => 'usd',
            'occurred_on' => '2026-10-01',
            'note' => '  обед ',
        ];
    }

    /** @return array<string, string> */
    private function errorsFor(array $data): array
    {
        try {
            TransactionInput::fromArray($data, $this->today);
        } catch (ValidationException $e) {
            return $e->errors;
        }

        $this->fail('Ожидалась ValidationException');
    }

    public function testValidInputIsParsed(): void
    {
        $input = TransactionInput::fromArray($this->valid(), $this->today);

        $this->assertSame(1, $input->categoryId);
        $this->assertSame(TransactionType::Expense, $input->type);
        $this->assertSame(125050, $input->amount->minor);
        $this->assertSame(Currency::USD, $input->amount->currency);
        $this->assertSame('обед', $input->note);
    }

    public function testAmountCanBeJsonNumber(): void
    {
        $input = TransactionInput::fromArray($this->valid(['amount' => 12.5]), $this->today);

        $this->assertSame(1250, $input->amount->minor);
    }

    public function testCollectsAllErrorsAtOnce(): void
    {
        $errors = $this->errorsFor([]);

        $this->assertEqualsCanonicalizing(['category_id', 'type', 'currency', 'occurred_on'], array_keys($errors));
    }

    public function testRejectsFutureDate(): void
    {
        $this->assertArrayHasKey('occurred_on', $this->errorsFor($this->valid(['occurred_on' => '2026-10-15'])));
    }

    public function testRejectsImpossibleDate(): void
    {
        $this->assertArrayHasKey('occurred_on', $this->errorsFor($this->valid(['occurred_on' => '2026-02-31'])));
    }

    public function testRejectsUnknownCurrencyAndBadAmount(): void
    {
        $this->assertArrayHasKey('currency', $this->errorsFor($this->valid(['currency' => 'XXX'])));
        $this->assertArrayHasKey('amount', $this->errorsFor($this->valid(['amount' => '-5'])));
        $this->assertArrayHasKey('amount', $this->errorsFor($this->valid(['amount' => ['1']])));
    }
}
