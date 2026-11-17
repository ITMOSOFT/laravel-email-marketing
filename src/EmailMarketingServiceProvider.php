<?php

namespace ItmoSoft\EmailMarketing;

use Illuminate\Support\ServiceProvider;

class EmailMarketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/email-marketing.php', 'itmosoft-email-marketing');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/email-marketing.php' => config_path('itmosoft-email-marketing.php'),
            ], 'itmosoft-email-marketing-config');
        }
    }
}
