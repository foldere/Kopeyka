<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Category;
use App\Domain\CategoryRepositoryInterface;
use App\Domain\ValidationException;

final class CategoryService
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    /** @return list<Category> */
    public function list(): array
    {
        return $this->categories->all();
    }

    public function create(string $name): Category
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Название — от 1 до 80 символов.']);
        }
        if ($this->categories->findByName($name) !== null) {
            throw new ValidationException(['name' => 'Категория с таким названием уже есть.']);
        }

        return $this->categories->add(new Category(null, $name));
    }
}
