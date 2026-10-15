<?php

declare(strict_types=1);

namespace Tests\Fake;

use App\Infrastructure\Http\HttpClientInterface;
use Closure;

/** HTTP-клиент для тестов: ответ даёт переданная функция, запрошенные адреса запоминаются. */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var list<string> */
    public array $requested = [];

    /** @param Closure(string): string $responder может бросить HttpException */
    public function __construct(private readonly Closure $responder)
    {
    }

    public function get(string $url): string
    {
        $this->requested[] = $url;

        return ($this->responder)($url);
    }
}
