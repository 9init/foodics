<?php

namespace App\Providers;

use App\Services\Payment\PaymentXmlBuilder;
use App\Services\Webhook\WebhookParserFactory;
use App\Services\Webhook\WebhookProcessor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WebhookParserFactory::class);
        $this->app->singleton(WebhookProcessor::class);
        $this->app->singleton(PaymentXmlBuilder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
