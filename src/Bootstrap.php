<?php

declare(strict_types=1);

namespace App;

use App\Application\CategoryService;
use App\Application\CurrencyConverter;
use App\Application\RateProviderInterface;
use App\Application\ReportService;
use App\Application\TransactionService;
use App\Domain\CategoryRepositoryInterface;
use App\Domain\ReportRepositoryInterface;
use App\Domain\TransactionRepositoryInterface;
use App\Http\ApiKernel;
use App\Http\CategoryController;
use App\Http\HealthController;
use App\Http\RateController;
use App\Http\ReportController;
use App\Http\Router;
use App\Http\TransactionController;
use App\Infrastructure\Http\StreamHttpClient;
use App\Infrastructure\Persistence\PdoCategoryRepository;
use App\Infrastructure\Persistence\PdoRateCache;
use App\Infrastructure\Persistence\PdoReportRepository;
use App\Infrastructure\Persistence\PdoTransactionRepository;
use App\Infrastructure\Rates\CachingRateProvider;
use App\Infrastructure\Rates\RateProviderFactory;
use App\Support\Clock;
use PDO;

/** «Корень композиции»: единственное место, где объекты собираются в приложение (ручной DI). */
final class Bootstrap
{
    /** Боевая сборка: репозитории на PDO, провайдер курсов из настроек (с кэшем в БД для внешних источников). */
    public static function kernelFromEnv(PDO $pdo, string $rateKind, Clock $clock): ApiKernel
    {
        $provider = RateProviderFactory::create($rateKind, new StreamHttpClient());
        if ($rateKind !== 'static') {
            $provider = new CachingRateProvider($provider, new PdoRateCache($pdo));
        }

        return self::kernel(
            new PdoTransactionRepository($pdo),
            new PdoCategoryRepository($pdo),
            new PdoReportRepository($pdo),
            $provider,
            $clock,
        );
    }

    /** Сборка из готовых интерфейсов: так же приложение собирается в тестах, но с заглушками. */
    public static function kernel(
        TransactionRepositoryInterface $transactions,
        CategoryRepositoryInterface $categories,
        ReportRepositoryInterface $reports,
        RateProviderInterface $rates,
        Clock $clock,
    ): ApiKernel {
        $converter = new CurrencyConverter($rates);

        $transactionController = new TransactionController(new TransactionService($transactions, $categories, $converter), $clock);
        $categoryController = new CategoryController(new CategoryService($categories));
        $reportController = new ReportController(new ReportService($reports), $clock);
        $rateController = new RateController($converter, $clock);
        $healthController = new HealthController();

        $router = new Router();
        $router->add('GET', '/api/health', [$healthController, 'show']);
        $router->add('GET', '/api/categories', [$categoryController, 'index']);
        $router->add('POST', '/api/categories', [$categoryController, 'store']);
        $router->add('GET', '/api/transactions', [$transactionController, 'index']);
        $router->add('POST', '/api/transactions', [$transactionController, 'store']);
        $router->add('GET', '/api/transactions/{id}', [$transactionController, 'show']);
        $router->add('PUT', '/api/transactions/{id}', [$transactionController, 'update']);
        $router->add('DELETE', '/api/transactions/{id}', [$transactionController, 'destroy']);
        $router->add('GET', '/api/reports/monthly', [$reportController, 'monthly']);
        $router->add('GET', '/api/reports/by-category', [$reportController, 'byCategory']);
        $router->add('GET', '/api/rates', [$rateController, 'show']);

        return new ApiKernel($router);
    }
}
