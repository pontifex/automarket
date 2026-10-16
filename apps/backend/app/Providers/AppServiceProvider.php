<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface::class,
            \AutoMarket\Infrastructure\Persistence\EloquentLedgerRepository::class
        );

        $this->app->bind(
            \AutoMarket\Application\Finance\Port\PaymentGatewayPort::class,
            \AutoMarket\Infrastructure\ExternalPayments\ResilientPaymentGatewayAdapter::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
