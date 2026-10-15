<?php

declare(strict_types=1);

namespace App\Domain;

use App\Support\Dates;
use DateTimeImmutable;

/** Условия поиска транзакций + пагинация. Создаётся только через fromQuery(): сразу проверенным. */
final class TransactionFilter
{
    public function __construct(
        public readonly ?DateTimeImmutable $from = null,
        public readonly ?DateTimeImmutable $to = null,
        public readonly ?int $categoryId = null,
        public readonly ?TransactionType $type = null,
        public readonly int $page = 1,
        public readonly int $perPage = 20,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** @param array<string, mixed> $query параметры строки запроса */
    public static function fromQuery(array $query): self
    {
        $get = static fn (string $key): ?string => isset($query[$key]) && is_string($query[$key]) && $query[$key] !== ''
            ? $query[$key]
            : null;

        $errors = [];
        $from = $to = $categoryId = $type = null;

        if (($raw = $get('from')) !== null && ($from = Dates::parse($raw)) === null) {
            $errors['from'] = 'Дата должна быть в формате ГГГГ-ММ-ДД.';
        }
        if (($raw = $get('to')) !== null && ($to = Dates::parse($raw)) === null) {
            $errors['to'] = 'Дата должна быть в формате ГГГГ-ММ-ДД.';
        }
        if ($from !== null && $to !== null && $to < $from) {
            $errors['to'] = 'Конечная дата раньше начальной.';
        }
        if (($raw = $get('category_id')) !== null) {
            if (ctype_digit($raw) && (int) $raw > 0) {
                $categoryId = (int) $raw;
            } else {
                $errors['category_id'] = 'Должно быть положительным целым числом.';
            }
        }
        if (($raw = $get('type')) !== null && ($type = TransactionType::tryFrom($raw)) === null) {
            $errors['type'] = 'Допустимо: income или expense.';
        }

        $page = 1;
        if (($raw = $get('page')) !== null) {
            if (ctype_digit($raw) && (int) $raw >= 1) {
                $page = (int) $raw;
            } else {
                $errors['page'] = 'Номер страницы — целое число от 1.';
            }
        }

        $perPage = 20;
        if (($raw = $get('per_page')) !== null) {
            if (ctype_digit($raw) && (int) $raw >= 1 && (int) $raw <= 100) {
                $perPage = (int) $raw;
            } else {
                $errors['per_page'] = 'Размер страницы — целое число от 1 до 100.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($from, $to, $categoryId, $type, $page, $perPage);
    }
}
