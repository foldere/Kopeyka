<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;

final class Request
{
    /** @param array<string, mixed> $query */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            (string) file_get_contents('php://input'),
        );
    }

    /**
     * Тело запроса как JSON-объект.
     *
     * @return array<string, mixed>
     * @throws JsonException если тело пустое, не JSON или не объект
     */
    public function json(): array
    {
        $data = json_decode($this->rawBody, true, 16, JSON_THROW_ON_ERROR);

        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new JsonException('Ожидается JSON-объект.');
        }

        return $data;
    }
}
