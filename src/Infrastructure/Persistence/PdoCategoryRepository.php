<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Category;
use App\Domain\CategoryRepositoryInterface;
use PDO;

final class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();

        return array_map(static fn (array $r): Category => new Category((int) $r['id'], $r['name']), $rows);
    }

    public function find(int $id): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : new Category((int) $row['id'], $row['name']);
    }

    public function findByName(string $name): ?Category
    {
        $stmt = $this->pdo->prepare('SELECT id, name FROM categories WHERE name = :name');
        $stmt->execute(['name' => $name]);
        $row = $stmt->fetch();

        return $row === false ? null : new Category((int) $row['id'], $row['name']);
    }

    public function add(Category $category): Category
    {
        $stmt = $this->pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        $stmt->execute(['name' => $category->name]);

        return new Category((int) $this->pdo->lastInsertId(), $category->name);
    }
}
