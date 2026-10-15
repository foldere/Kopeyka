<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\NotFoundException;

/** Сопоставляет «метод + путь» с обработчиком; понимает параметры вида {id}. */
final class Router
{
    /** @var list<array{string, string, callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(Request $request): JsonResponse
    {
        $allowed = [];

        foreach ($this->routes as [$method, $regex, $handler]) {
            if (preg_match($regex, $request->path, $matches) !== 1) {
                continue;
            }
            if ($method !== $request->method) {
                $allowed[] = $method;
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return $handler($request, ...$params);
        }

        throw $allowed !== []
            ? new MethodNotAllowedException(array_values(array_unique($allowed)))
            : new NotFoundException('Маршрут не найден.');
    }
}
