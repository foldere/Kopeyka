<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\TransactionType;
use App\Domain\ValidationException;
use DateTimeImmutable;
use InvalidArgumentException;
use App\Support\Dates;

/** Проверенные входные данные для создания/изменения транзакции. */
final class TransactionInput
{
    public function __construct(
        public readonly int $categoryId,
        public readonly TransactionType $type,
        public readonly Money $amount,
        public readonly DateTimeImmutable $occurredOn,
        public readonly string $note = '',
    ) {
    }

    /**
     * Разбирает тело запроса. Не останавливается на первой ошибке, а собирает все:
     * клиенту удобнее исправить сразу всё.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public static function fromArray(array $data, DateTimeImmutable $today): self
    {
        $errors = [];

        $categoryId = $data['category_id'] ?? null;
        if (is_string($categoryId) && ctype_digit($categoryId)) {
            $categoryId = (int) $categoryId;
        }
        if (!is_int($categoryId) || $categoryId < 1) {
            $errors['category_id'] = 'Укажите id категории (положительное целое число).';
        }

        $type = is_string($data['type'] ?? null) ? TransactionType::tryFrom($data['type']) : null;
        if ($type === null) {
            $errors['type'] = 'Допустимо: income или expense.';
        }

        $currency = is_string($data['currency'] ?? null) ? Currency::tryFrom(strtoupper($data['currency'])) : null;
        if ($currency === null) {
            $errors['currency'] = 'Допустимые валюты: ' . implode(', ', array_column(Currency::cases(), 'value')) . '.';
        }

        $amount = null;
        $rawAmount = $data['amount'] ?? null;
        if ($currency !== null) {
            if (is_string($rawAmount) || is_int($rawAmount) || is_float($rawAmount)) {
                try {
                    $amount = Money::fromDecimalString((string) $rawAmount, $currency);
                } catch (InvalidArgumentException $e) {
                    $errors['amount'] = $e->getMessage();
                }
            } else {
                $errors['amount'] = 'Укажите сумму числом или строкой, например 1250.50.';
            }
        }

        $occurredOn = is_string($data['occurred_on'] ?? null) ? Dates::parse($data['occurred_on']) : null;
        if ($occurredOn === null) {
            $errors['occurred_on'] = 'Дата должна быть в формате ГГГГ-ММ-ДД.';
        } elseif ($occurredOn > $today) {
            $errors['occurred_on'] = 'Дата не может быть в будущем.';
        }

        $note = $data['note'] ?? '';
        if (!is_string($note) || mb_strlen(trim($note)) > 255) {
            $errors['note'] = 'Заметка — строка не длиннее 255 символов.';
            $note = '';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($categoryId, $type, $amount, $occurredOn, trim($note));
    }
}
