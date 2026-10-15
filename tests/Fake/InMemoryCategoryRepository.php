<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Domain\Category;
use App\Domain\CategoryRepositoryInterface;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    private array $items = [];
    private int $nextId = 1;

    public function all(): array
    {
        return array_values($this->items);
    }

    public function find(int $id): ?Category
    {
        return $this->items[$id] ?? null;
    }

    public function findByName(string $name): ?Category
    {
        foreach ($this->items as $category) {
            if ($category->name === $name) {
                return $category;
            }
        }

        return null;
    }

    public function add(Category $category): Category
    {
        $saved = new Category($this->nextId++, $category->name);
        $this->items[$saved->id] = $saved;

        return $saved;
    }
}
