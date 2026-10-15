<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

interface HttpClientInterface
{
    /** @throws HttpException при ошибке сети или ответе со статусом 4xx/5xx */
    public function get(string $url): string;
}
