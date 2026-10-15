<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\CurrencyConverter;
use App\Domain\Rate;
use App\Domain\ValidationException;
use App\Support\Clock;
use App\Support\Dates;

final class RateController
{
    public function __construct(
        private readonly CurrencyConverter $converter,
        private readonly Clock $clock,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $raw = $request->query['date'] ?? null;
        $date = $raw === null ? $this->clock->today() : (is_string($raw) ? Dates::parse($raw) : null);
        if ($date === null) {
            throw new ValidationException(['date' => 'Дата должна быть в формате ГГГГ-ММ-ДД.']);
        }

        return new JsonResponse(200, ['data' => [
            'date' => $date->format('Y-m-d'),
            'base' => 'RUB',
            'rates' => array_map([Rate::class, 'format'], $this->converter->ratesOn($date)),
        ]]);
    }
}
