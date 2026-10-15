<?php

declare(strict_types=1);

namespace Tests;

use App\Bootstrap;
use App\Http\ApiKernel;
use App\Http\JsonResponse;
use App\Http\Request;
use App\Infrastructure\Rates\StaticRateProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fake\EmptyReportRepository;
use Tests\Fake\FixedClock;
use Tests\Fake\InMemoryCategoryRepository;
use Tests\Fake\InMemoryTransactionRepository;

/** Сквозные тесты API: запрос → маршрутизация → сервисы → ответ. База данных не нужна. */
final class ApiTest extends TestCase
{
    private ApiKernel $kernel;

    protected function setUp(): void
    {
        $this->kernel = Bootstrap::kernel(
            new InMemoryTransactionRepository(),
            new InMemoryCategoryRepository(),
            new EmptyReportRepository(),
            new StaticRateProvider(),
            new FixedClock('2026-10-14'),
        );
    }

    /** @param array<string, mixed>|string|null $body */
    private function call(string $method, string $path, array|string|null $body = null, array $query = []): JsonResponse
    {
        $raw = is_array($body) ? json_encode($body, JSON_UNESCAPED_UNICODE) : (string) $body;

        return $this->kernel->handle(new Request($method, $path, $query, $raw));
    }

    private function decode(JsonResponse $response): array
    {
        return json_decode($response->body(), true);
    }

    private function createCategory(): int
    {
        $response = $this->call('POST', '/api/categories', ['name' => 'Еда']);
        $this->assertSame(201, $response->status);

        return $this->decode($response)['data']['id'];
    }

    /** @return array<string, mixed> */
    private function payload(int $categoryId, array $override = []): array
    {
        return $override + [
            'category_id' => $categoryId,
            'type' => 'expense',
            'amount' => '10.00',
            'currency' => 'USD',
            'occurred_on' => '2026-10-10',
            'note' => 'кофе',
        ];
    }

    public function testHealth(): void
    {
        $this->assertSame(200, $this->call('GET', '/api/health')->status);
    }

    public function testCreateTransactionConvertsCurrency(): void
    {
        $category = $this->createCategory();

        $response = $this->call('POST', '/api/transactions', $this->payload($category));
        $data = $this->decode($response)['data'];

        $this->assertSame(201, $response->status);
        $this->assertSame('/api/transactions/1', $response->headers['Location']);
        $this->assertSame('10.00', $data['amount']);
        $this->assertSame('95.000000', $data['rate']);
        $this->assertSame('950.00', $data['amount_rub']);
    }

    public function testListIsPaginatedAndFiltered(): void
    {
        $category = $this->createCategory();
        foreach (['2026-10-01', '2026-10-05', '2026-10-09'] as $day) {
            $this->call('POST', '/api/transactions', $this->payload($category, ['occurred_on' => $day, 'currency' => 'RUB']));
        }

        $all = $this->decode($this->call('GET', '/api/transactions', null, ['per_page' => '2']));
        $this->assertCount(2, $all['data']);
        $this->assertSame(3, $all['meta']['total']);
        $this->assertSame(2, $all['meta']['total_pages']);
        $this->assertSame('2026-10-09', $all['data'][0]['occurred_on'], 'новые — первыми');

        $filtered = $this->decode($this->call('GET', '/api/transactions', null, ['from' => '2026-10-04', 'to' => '2026-10-06']));
        $this->assertSame(1, $filtered['meta']['total']);
    }

    public function testValidationErrorsAre422WithDetails(): void
    {
        $response = $this->call('POST', '/api/transactions', ['amount' => '-1']);
        $error = $this->decode($response)['error'];

        $this->assertSame(422, $response->status);
        $this->assertSame('validation_failed', $error['code']);
        $this->assertArrayHasKey('amount', $error['details'] + ['amount' => '']);
        $this->assertArrayHasKey('category_id', $error['details']);
    }

    public function testUnknownCategoryIsRejected(): void
    {
        $response = $this->call('POST', '/api/transactions', $this->payload(999));

        $this->assertSame(422, $response->status);
        $this->assertArrayHasKey('category_id', $this->decode($response)['error']['details']);
    }

    public function testInvalidJsonIs400(): void
    {
        $this->assertSame(400, $this->call('POST', '/api/transactions', '{broken')->status);
        $this->assertSame(400, $this->call('POST', '/api/transactions', '[1,2]')->status);
    }

    public function testUpdateAndDelete(): void
    {
        $category = $this->createCategory();
        $this->call('POST', '/api/transactions', $this->payload($category));

        $updated = $this->call('PUT', '/api/transactions/1', $this->payload($category, ['amount' => '20.00']));
        $this->assertSame(200, $updated->status);
        $this->assertSame('1900.00', $this->decode($updated)['data']['amount_rub']);

        $this->assertSame(204, $this->call('DELETE', '/api/transactions/1')->status);
        $this->assertSame(404, $this->call('GET', '/api/transactions/1')->status);
        $this->assertSame(404, $this->call('DELETE', '/api/transactions/1')->status);
    }

    public function testBadIdAndUnknownRouteAre404(): void
    {
        $this->assertSame(404, $this->call('GET', '/api/transactions/abc')->status);
        $this->assertSame(404, $this->call('GET', '/api/transactions/0')->status);
        $this->assertSame(404, $this->call('GET', '/api/nothing')->status);
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $response = $this->call('PATCH', '/api/transactions/1');

        $this->assertSame(405, $response->status);
        $this->assertSame('GET, PUT, DELETE', $response->headers['Allow']);
    }

    public function testDuplicateCategoryIsRejected(): void
    {
        $this->createCategory();

        $this->assertSame(422, $this->call('POST', '/api/categories', ['name' => 'Еда'])->status);
    }

    public function testRatesEndpoint(): void
    {
        $data = $this->decode($this->call('GET', '/api/rates', null, ['date' => '2026-10-14']))['data'];

        $this->assertSame('95.000000', $data['rates']['USD']);
        $this->assertSame('RUB', $data['base']);
    }

    public function testReportsValidateTheirParameters(): void
    {
        $this->assertSame(200, $this->call('GET', '/api/reports/monthly')->status);
        $this->assertSame(422, $this->call('GET', '/api/reports/monthly', null, ['from' => 'вчера'])->status);
        $this->assertSame(422, $this->call('GET', '/api/reports/by-category', null, ['type' => 'xxx'])->status);
    }
}
