<?php

declare(strict_types=1);

namespace App\Domain;

/** Поддерживаемые валюты. Новая валюта = новая строка здесь. */
enum Currency: string
{
    case RUB = 'RUB';
    case USD = 'USD';
    case EUR = 'EUR';
    case CNY = 'CNY';
    case GBP = 'GBP';
    case KZT = 'KZT';
}
