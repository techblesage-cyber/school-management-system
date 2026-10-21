<?php

namespace App\Providers\Filament;

use App\Filament\School\Pages\Tenancy\EditSchoolProfile;
use App\Models\School;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Shared\ChoosePassword;
use App\Filament\Shared\Home;
use App\Filament\Shared\InitialsAvatars;
use App\Filament\Shared\Theme;
use App\Http\Middleware\RequireOwnPassword;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Each school's own portal. Every page runs inside one school (the tenant),
 * and records are scoped to that school automatically.
 */
class SchoolPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = Theme::apply($panel)
            ->id('school')
            ->path('school')
            ->login()
            // "Forgot password?" emails a reset link
            ->passwordReset()
            ->brandName('School Portal')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->tenant(School::class, slugAttribute: 'slug')
            ->tenantProfile(EditSchoolProfile::class)
            ->searchableTenantMenu()
            ->spa()
            ->defaultAvatarProvider(InitialsAvatars::class)
            ->navigationGroups(['Students', 'Academics', 'Fees', 'Communication', 'Setup'])
            ->discoverResources(in: app_path('Filament/School/Resources'), for: 'App\Filament\School\Resources')
            ->discoverPages(in: app_path('Filament/School/Pages'), for: 'App\Filament\School\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/School/Widgets'), for: 'App\Filament\School\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireOwnPassword::class,
            ])
            // First sign-in with a password from the system: choose your own
            ->authenticatedRoutes(fn () => Route::get('/choose-password', ChoosePassword::class)->name('auth.choose-password'));

        // Live server: one subdomain per school (see config/school.php)
        if (filled($domain = config('school.tenant_domain'))) {
            $panel->tenantDomain('{tenant:slug}.'.$domain);
        }

        return $panel;
    }
}
