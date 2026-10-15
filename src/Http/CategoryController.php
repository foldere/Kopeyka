<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\CategoryService;
use App\Domain\ValidationException;

final class CategoryController
{
    public function __construct(private readonly CategoryService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse(200, [
            'data' => array_map(static fn ($c): array => $c->toArray(), $this->service->list()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $name = $request->json()['name'] ?? null;
        if (!is_string($name)) {
            throw new ValidationException(['name' => 'Укажите название строкой.']);
        }

        return new JsonResponse(201, ['data' => $this->service->create($name)->toArray()]);
    }
}
