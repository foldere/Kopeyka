<?php

declare(strict_types=1);

namespace App\Infrastructure\Rates;

use App\Application\RateProviderInterface;
use App\Domain\Currency;
use App\Domain\Rate;
use App\Domain\RateUnavailableException;
use App\Infrastructure\Http\HttpClientInterface;
use App\Infrastructure\Http\HttpException;
use DateTimeImmutable;
use JsonException;

/**
 * Курсы ЦБ РФ в формате JSON (зеркало cbr-xml-daily.ru).
 * На выходные и праздники архива нет (404), поэтому при 404 берём курс предыдущего дня.
 */
final class CbrJsonRateProvider implements RateProviderInterface
{
    private const MAX_DAYS_BACK = 7;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl = 'https://www.cbr-xml-daily.ru',
    ) {
    }

    public function ratesOn(DateTimeImmutable $date): array
    {
        for ($back = 0; $back <= self::MAX_DAYS_BACK; $back++) {
            $day = $date->modify("-{$back} days");
            $url = $this->baseUrl . '/archive/' . $day->format('Y/m/d') . '/daily_json.js';

            try {
                return $this->parse($this->http->get($url));
            } catch (HttpException $e) {
                if ($e->status !== 404) {
                    throw new RateUnavailableException('Не удалось получить курсы ЦБ (JSON): ' . $e->getMessage(), 0, $e);
                }
            }
        }

        throw new RateUnavailableException('Не нашли курсы ЦБ за последние ' . self::MAX_DAYS_BACK . ' дней.');
    }

    /** @return array<string, int> */
    private function parse(string $json): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RateUnavailableException('ЦБ вернул некорректный JSON.', 0, $e);
        }

        if (!isset($data['Valute']) || !is_array($data['Valute'])) {
            throw new RateUnavailableException('ЦБ вернул JSON в неожиданном формате.');
        }

        $rates = [];
        foreach ($data['Valute'] as $code => $valute) {
            if (Currency::tryFrom((string) $code) === null) {
                continue;
            }
            $rates[(string) $code] = Rate::parseMicro((string) $valute['Value'], (int) $valute['Nominal']);
        }

        return $rates;
    }
}
