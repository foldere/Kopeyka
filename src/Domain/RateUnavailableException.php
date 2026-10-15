<?php

declare(strict_types=1);

namespace App\Domain;

use RuntimeException;

/** Не удалось получить курс валюты (источник недоступен или в нём нет нужной валюты). */
final class RateUnavailableException extends RuntimeException
{
}
