<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Codespaces shows the app over https but passes requests to Laravel
        // over http. Force https links there so styles and scripts load.
        // Only matches *.app.github.dev, so the live server is unaffected.
        if (! $this->app->runningInConsole() && str_ends_with(request()->getHost(), '.app.github.dev')) {
            URL::forceScheme('https');
        }
    }
}
