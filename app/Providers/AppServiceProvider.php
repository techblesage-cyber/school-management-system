<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Sharebility super admins may do everything, in every school.
        // Everyone else is checked against their role (see app/Policies).
        Gate::before(fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        // GitHub Codespaces shows the app over https, but passes requests to
        // Laravel over plain http. Without this, pages link their styles and
        // scripts with http:// and the browser blocks them (unstyled pages).
        // Only matches *.app.github.dev, so it never affects the live server.
        if (! $this->app->runningInConsole() && str_ends_with(request()->getHost(), '.app.github.dev')) {
            URL::forceScheme('https');
        }
    }
}
