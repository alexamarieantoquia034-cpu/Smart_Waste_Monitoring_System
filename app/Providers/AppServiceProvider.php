<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
    public function boot(): void
    {
        // Behind a TLS-terminating proxy (Railway, Heroku, nginx, Cloudflare)
        // PHP sees the connection as plain HTTP, so asset()/route() would emit
        // http:// URLs. On an https:// page the browser then blocks them as
        // mixed content and the compiled CSS/JS silently fails to load.
        // Forcing the scheme keeps generated URLs on https everywhere.
        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }
    }
}
