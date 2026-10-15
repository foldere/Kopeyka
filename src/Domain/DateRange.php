<?php

declare(strict_types=1);

namespace App\Domain;

use App\Support\Dates;
use DateTimeImmutable;

/** Период для отчётов. По умолчанию — с 1 января текущего года по сегодня. */
final class DateRange
{
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
    ) {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query, DateTimeImmutable $today): self
    {
        $errors = [];
        $from = new DateTimeImmutable($today->format('Y') . '-01-01');
        $to = $today;

        foreach (['from', 'to'] as $key) {
            if (!isset($query[$key]) || $query[$key] === '') {
                continue;
            }
            $parsed = is_string($query[$key]) ? Dates::parse($query[$key]) : null;
            if ($parsed === null) {
                $errors[$key] = 'Дата должна быть в формате ГГГГ-ММ-ДД.';
            } elseif ($key === 'from') {
                $from = $parsed;
            } else {
                $to = $parsed;
            }
        }

        if ($errors === [] && $to < $from) {
            $errors['to'] = 'Конечная дата раньше начальной.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return new self($from, $to);
    }
}
