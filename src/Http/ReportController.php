<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\ReportService;
use App\Domain\DateRange;
use App\Domain\TransactionType;
use App\Domain\ValidationException;
use App\Support\Clock;

final class ReportController
{
    public function __construct(
        private readonly ReportService $service,
        private readonly Clock $clock,
    ) {
    }

    public function monthly(Request $request): JsonResponse
    {
        $range = DateRange::fromQuery($request->query, $this->clock->today());

        return new JsonResponse(200, [
            'data' => $this->service->monthly($range),
            'meta' => $this->meta($range),
        ]);
    }

    public function byCategory(Request $request): JsonResponse
    {
        $range = DateRange::fromQuery($request->query, $this->clock->today());

        $rawType = $request->query['type'] ?? 'expense';
        $type = is_string($rawType) ? TransactionType::tryFrom($rawType) : null;
        if ($type === null) {
            throw new ValidationException(['type' => 'Допустимо: income или expense.']);
        }

        return new JsonResponse(200, [
            'data' => $this->service->byCategory($type, $range),
            'meta' => $this->meta($range) + ['type' => $type->value],
        ]);
    }

    /** @return array<string, string> */
    private function meta(DateRange $range): array
    {
        return [
            'from' => $range->from->format('Y-m-d'),
            'to' => $range->to->format('Y-m-d'),
            'currency' => 'RUB',
        ];
    }
}
