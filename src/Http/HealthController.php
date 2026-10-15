<?php

declare(strict_types=1);

namespace App\Http;

final class HealthController
{
    public function show(Request $request): JsonResponse
    {
        return new JsonResponse(200, ['status' => 'ok']);
    }
}
