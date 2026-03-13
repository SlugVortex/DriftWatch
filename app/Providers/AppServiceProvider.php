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
        //
    }

    /**
     * Bootstrap any application services.
     */
        public function boot()
    {
        $isTunnel = str_contains(request()->getHost(), 'trycloudflare.com');
        $isProduction = app()->isProduction(); // Checks if APP_ENV is 'production'
        $isLocalHost = in_array(request()->getHost(), ['127.0.0.1', 'localhost']);

        // Only force HTTPS if we are in a tunnel OR production,
        // AND we are NOT on a local dev address.
        if (($isTunnel || $isProduction) && !$isLocalHost) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

}
