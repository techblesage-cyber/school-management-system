<?php

namespace App\Providers\Filament;

use App\Filament\Parent\Pages\EditParentProfile;
use App\Filament\Parent\Pages\ParentLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Shared\ChoosePassword;
use App\Filament\Shared\Home;
use App\Filament\Shared\InitialsAvatars;
use App\Filament\Shared\Theme;
use App\Http\Middleware\RequireOwnPassword;
use App\Support\ParentPortal;
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
 * The parents' portal: parents sign in with their phone number and follow
 * their children's results, report cards, fees, attendance and circulars.
 */
class ParentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return Theme::apply($panel)
            ->id('parent')
            ->path('parent')
            ->authGuard('parent')
            ->spa()
            ->defaultAvatarProvider(InitialsAvatars::class)
            ->login(ParentLogin::class)
            ->profile(EditParentProfile::class, isSimple: false)
            ->brandName(fn (): string => ParentPortal::brandName())
            ->brandLogo(fn (): ?string => ParentPortal::brandLogo())
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Parent/Resources'), for: 'App\Filament\Parent\Resources')
            ->discoverPages(in: app_path('Filament/Parent/Pages'), for: 'App\Filament\Parent\Pages')
            ->pages([
                Home::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Parent/Widgets'), for: 'App\Filament\Parent\Widgets')
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
    }
}
