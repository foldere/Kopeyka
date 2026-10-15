<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\NotFoundException;
use App\Domain\RateUnavailableException;
use App\Domain\ValidationException;
use JsonException;
use Throwable;

/** Единое место, где исключения превращаются в HTTP-ответы с понятным JSON. */
final class ApiKernel
{
    public function __construct(private readonly Router $router)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            return $this->router->dispatch($request);
        } catch (ValidationException $e) {
            return $this->error(422, 'validation_failed', $e->getMessage(), $e->errors);
        } catch (NotFoundException $e) {
            return $this->error(404, 'not_found', $e->getMessage());
        } catch (MethodNotAllowedException $e) {
            return $this->error(405, 'method_not_allowed', $e->getMessage(), [], ['Allow' => implode(', ', $e->allowed)]);
        } catch (JsonException) {
            return $this->error(400, 'invalid_json', 'Тело запроса должно быть корректным JSON-объектом.');
        } catch (RateUnavailableException $e) {
            return $this->error(502, 'rates_unavailable', $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e); // подробности — в лог, клиенту — только общее сообщение
            return $this->error(500, 'internal_error', 'Внутренняя ошибка сервера.');
        }
    }

    /**
     * @param array<string, string> $details
     * @param array<string, string> $headers
     */
    private function error(int $status, string $code, string $message, array $details = [], array $headers = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }

        return new JsonResponse($status, ['error' => $error], $headers);
    }
}
