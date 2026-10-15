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

/**
 * Курсы ЦБ РФ в формате XML.
 * Формат: <ValCurs><Valute><CharCode>USD</CharCode><Nominal>1</Nominal><Value>95,1234</Value>...
 * Ответ приходит в кодировке windows-1251 — SimpleXML сам перекодирует её по заголовку XML.
 */
final class CbrXmlRateProvider implements RateProviderInterface
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $baseUrl = 'https://www.cbr.ru',
    ) {
    }

    public function ratesOn(DateTimeImmutable $date): array
    {
        $url = $this->baseUrl . '/scripts/XML_daily.asp?date_req=' . $date->format('d/m/Y');

        try {
            $body = $this->http->get($url);
        } catch (HttpException $e) {
            throw new RateUnavailableException('Не удалось получить курсы ЦБ (XML): ' . $e->getMessage(), 0, $e);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if ($xml === false || !isset($xml->Valute)) {
            throw new RateUnavailableException('ЦБ вернул XML в неожиданном формате.');
        }

        $rates = [];
        foreach ($xml->Valute as $valute) {
            $code = (string) $valute->CharCode;
            if (Currency::tryFrom($code) === null) {
                continue; // нужны только поддерживаемые валюты
            }
            $rates[$code] = Rate::parseMicro((string) $valute->Value, (int) $valute->Nominal);
        }

        return $rates;
    }
}
