<?php

namespace App\Providers;

use App\Services\Sms\AfricasTalkingDriver;
use App\Services\Sms\LogDriver;
use App\Services\Sms\SmsDriver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // How text messages are sent: written to the log, or through Africa's Talking
        $this->app->bind(SmsDriver::class, fn (): SmsDriver => match (config('sms.driver')) {
            'africastalking' => AfricasTalkingDriver::fromConfig(),
            default => new LogDriver,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // GitHub Codespaces shows the app over https, but passes requests to
        // Laravel over plain http. Without this, pages link their styles and
        // scripts with http:// and the browser blocks them (unstyled pages).
        // Only matches *.app.github.dev, so it never affects the live server.
        if (! $this->app->runningInConsole() && str_ends_with(request()->getHost(), '.app.github.dev')) {
            URL::forceScheme('https');
        }
    }
}
