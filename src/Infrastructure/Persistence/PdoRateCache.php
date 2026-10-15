<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\RateCacheInterface;
use DateTimeImmutable;
use PDO;

final class PdoRateCache implements RateCacheInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(DateTimeImmutable $date): array
    {
        $stmt = $this->pdo->prepare('SELECT currency, rub_per_unit_micro FROM exchange_rates WHERE rate_date = :d');
        $stmt->execute(['d' => $date->format('Y-m-d')]);

        $rates = [];
        foreach ($stmt->fetchAll() as $row) {
            $rates[$row['currency']] = (int) $row['rub_per_unit_micro'];
        }

        return $rates;
    }

    public function store(DateTimeImmutable $date, array $rates): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO exchange_rates (rate_date, currency, rub_per_unit_micro)
             VALUES (:d, :c, :r)
             ON DUPLICATE KEY UPDATE rub_per_unit_micro = VALUES(rub_per_unit_micro)'
        );

        $this->pdo->beginTransaction();
        foreach ($rates as $currency => $micro) {
            $stmt->execute(['d' => $date->format('Y-m-d'), 'c' => $currency, 'r' => $micro]);
        }
        $this->pdo->commit();
    }
}
