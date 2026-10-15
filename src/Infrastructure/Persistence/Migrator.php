<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use PDO;

/**
 * Версионные миграции: файлы database/migrations/NNN_name.sql применяются по порядку,
 * применённые запоминаются в таблице schema_migrations.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
    ) {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(100) PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /** @return list<string> версии уже применённых миграций */
    public function applied(): array
    {
        return $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> версии ещё не применённых миграций */
    public function pending(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files);

        $versions = array_map(static fn (string $f): string => basename($f, '.sql'), $files);

        return array_values(array_diff($versions, $this->applied()));
    }

    /**
     * @param int|null $limit сколько миграций применить (null — все)
     * @return list<string> применённые сейчас версии
     */
    public function run(?int $limit = null): array
    {
        $done = [];
        foreach ($this->pending() as $version) {
            if ($limit !== null && count($done) >= $limit) {
                break;
            }

            foreach ($this->statements($this->directory . '/' . $version . '.sql') as $sql) {
                $this->pdo->exec($sql);
            }
            $stmt = $this->pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:v)');
            $stmt->execute(['v' => $version]);
            $done[] = $version;
        }

        return $done;
    }

    /** @return list<string> */
    private function statements(string $file): array
    {
        $sql = (string) preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));

        return array_values(array_filter(array_map('trim', explode(';', $sql)), static fn (string $s): bool => $s !== ''));
    }
}
