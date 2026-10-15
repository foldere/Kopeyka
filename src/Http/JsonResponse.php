<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly mixed $data = null,
        public readonly array $headers = [],
    ) {
    }

    public function body(): string
    {
        return $this->data === null
            ? ''
            : json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body();
    }
}
