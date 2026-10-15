<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\TransactionInput;
use App\Application\TransactionService;
use App\Domain\TransactionFilter;
use App\Support\Clock;
use App\Support\Id;

final class TransactionController
{
    public function __construct(
        private readonly TransactionService $service,
        private readonly Clock $clock,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filter = TransactionFilter::fromQuery($request->query);
        $result = $this->service->list($filter);

        return new JsonResponse(200, [
            'data' => array_map(static fn ($t): array => $t->toArray(), $result['items']),
            'meta' => [
                'page' => $filter->page,
                'per_page' => $filter->perPage,
                'total' => $result['total'],
                'total_pages' => (int) ceil($result['total'] / $filter->perPage),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse(200, ['data' => $this->service->get(Id::parse($id))->toArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = TransactionInput::fromArray($request->json(), $this->clock->today());
        $transaction = $this->service->create($input);

        return new JsonResponse(
            201,
            ['data' => $transaction->toArray()],
            ['Location' => '/api/transactions/' . $transaction->id],
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $input = TransactionInput::fromArray($request->json(), $this->clock->today());

        return new JsonResponse(200, ['data' => $this->service->update(Id::parse($id), $input)->toArray()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->service->delete(Id::parse($id));

        return new JsonResponse(204);
    }
}
