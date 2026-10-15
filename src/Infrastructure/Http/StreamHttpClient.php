<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/** Простой HTTP-клиент на file_get_contents (без расширения curl). */
final class StreamHttpClient implements HttpClientInterface
{
    public function __construct(private readonly int $timeoutSeconds = 5)
    {
    }

    public function get(string $url): string
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $this->timeoutSeconds,
            'ignore_errors' => true, // чтобы получить тело и статус даже при 404/500
            'header' => "User-Agent: kopeyka/1.0\r\n",
        ]]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new HttpException("Не удалось выполнить запрос к {$url}.");
        }

        $status = 200;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1]; // последняя строка статуса — после редиректов
            }
        }
        if ($status >= 400) {
            throw new HttpException("Источник ответил статусом {$status}: {$url}", $status);
        }

        return $body;
    }
}
