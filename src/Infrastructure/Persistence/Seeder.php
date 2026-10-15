<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\CategoryService;
use App\Application\TransactionInput;
use App\Application\TransactionService;
use App\Domain\Currency;
use App\Domain\Money;
use App\Domain\TransactionType;
use App\Support\Clock;
use PDO;
use RuntimeException;

/** Тестовые данные: небольшой демо-набор и большой набор для экспериментов с индексами. */
final class Seeder
{
    private const EXPENSE_CATEGORIES = ['Еда', 'Транспорт', 'Жильё', 'Развлечения', 'Здоровье'];
    private const INCOME_CATEGORIES = ['Зарплата', 'Подработка'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly CategoryService $categories,
        private readonly TransactionService $transactions,
        private readonly Clock $clock,
    ) {
    }

    /** Около 70 транзакций за последние полгода. @return int сколько создано */
    public function demo(): int
    {
        if ($this->categories->list() !== []) {
            return 0;
        }

        $ids = [];
        foreach ([...self::EXPENSE_CATEGORIES, ...self::INCOME_CATEGORIES] as $name) {
            $ids[$name] = $this->categories->create($name)->id;
        }

        mt_srand(42); // фиксированное зерно: каждый раз одни и те же данные
        $today = $this->clock->today();
        $created = 0;

        for ($i = 0; $i < 60; $i++) {
            $name = self::EXPENSE_CATEGORIES[array_rand(self::EXPENSE_CATEGORIES)];
            $foreign = $i % 10 === 9;
            $currency = $foreign ? (mt_rand(0, 1) === 0 ? Currency::USD : Currency::EUR) : Currency::RUB;
            $minor = $foreign ? mt_rand(1000, 8000) : mt_rand(15_000, 900_000);

            $this->transactions->create(new TransactionInput(
                $ids[$name],
                TransactionType::Expense,
                Money::ofMinor($minor, $currency),
                $today->modify('-' . mt_rand(0, 180) . ' days'),
                $foreign ? 'Покупка за границей' : '',
            ));
            $created++;
        }

        for ($m = 0; $m < 6; $m++) {
            $this->transactions->create(new TransactionInput(
                $ids['Зарплата'],
                TransactionType::Income,
                Money::ofMinor(12_000_000, Currency::RUB),
                $today->modify("-{$m} months")->modify('first day of this month')->modify('+4 days'),
                'Зарплата',
            ));
            $created++;
        }

        return $created;
    }

    /**
     * Много строк для проверки индексов и EXPLAIN. Вставка пачками по 1000 внутри транзакции —
     * так быстрее, чем по одной.
     */
    public function bulk(int $count): int
    {
        $ids = array_map(static fn ($c): int => (int) $c->id, $this->categories->list());
        if ($ids === []) {
            throw new RuntimeException('Сначала создайте категории: php bin/console seed:demo');
        }

        mt_srand(7);
        $base = $this->clock->today()->getTimestamp();
        $this->pdo->beginTransaction();

        for ($done = 0; $done < $count; $done += 1000) {
            $batch = min(1000, $count - $done);
            $placeholders = implode(',', array_fill(0, $batch, '(?,?,?,?,?,?,?,?)'));
            $values = [];

            for ($i = 0; $i < $batch; $i++) {
                $minor = mt_rand(10_000, 500_000);
                array_push(
                    $values,
                    $ids[array_rand($ids)],
                    mt_rand(1, 10) <= 8 ? 'expense' : 'income',
                    $minor,
                    'RUB',
                    1_000_000,
                    $minor,
                    date('Y-m-d', $base - mt_rand(0, 1095) * 86400),
                    '',
                );
            }

            $this->pdo
                ->prepare("INSERT INTO transactions (category_id, type, amount_minor, currency, rate_micro, amount_rub_minor, occurred_on, note) VALUES {$placeholders}")
                ->execute($values);
        }

        $this->pdo->commit();

        return $count;
    }
}
