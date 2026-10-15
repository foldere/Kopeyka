<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Support\Env;
use PDO;

final class Connection
{
    public static function fromEnv(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', 'db'),
            Env::get('DB_NAME', 'kopeyka'),
        );

        return new PDO($dsn, Env::get('DB_USER', 'kopeyka'), Env::get('DB_PASS', 'secret'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
