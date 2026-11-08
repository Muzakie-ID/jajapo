<?php

namespace App\Providers;

use App\Payments\FakeGateway;
use App\Payments\HttpGateway;
use App\Payments\PaymentGateway;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            $driver = config('services.payment_gateway.driver');

            // Gateway palsu menandai lunas tanpa uang masuk; jangan sampai aktif di produksi.
            if ($driver !== 'http' && $app->environment('production')) {
                throw new RuntimeException('PAYMENT_GATEWAY_DRIVER harus "http" di produksi.');
            }

            return $driver === 'http' ? new HttpGateway : new FakeGateway;
        });
    }

    public function boot(): void
    {
        //
    }
}
