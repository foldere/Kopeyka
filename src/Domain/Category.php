<?php

declare(strict_types=1);

namespace App\Domain;

final class Category
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
