<?php

declare(strict_types=1);

namespace App\Domain;

interface CategoryRepositoryInterface
{
    /** @return list<Category> */
    public function all(): array;

    public function find(int $id): ?Category;

    public function findByName(string $name): ?Category;

    public function add(Category $category): Category;
}
