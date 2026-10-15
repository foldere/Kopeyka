<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Http\Request;
use App\Infrastructure\Persistence\Connection;
use App\Support\Env;
use App\Support\SystemClock;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set(Env::get('APP_TZ', 'Asia/Yekaterinburg'));

$kernel = Bootstrap::kernelFromEnv(Connection::fromEnv(), Env::get('RATE_PROVIDER', 'static'), new SystemClock());
$kernel->handle(Request::fromGlobals())->send();
