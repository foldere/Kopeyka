<?php

declare(strict_types=1);

namespace App\Support;

final class Env
{
    public static function get(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
