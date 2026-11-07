<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\HttpGateway;
use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, fn () => match (config('services.payment_gateway.driver')) {
            'http' => new HttpGateway,
            default => new FakeGateway,
        });
    }

    public function boot(): void
    {
        //
    }
}
