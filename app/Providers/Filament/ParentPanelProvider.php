<?php

namespace App\Providers\Filament;

use App\Filament\Parent\Pages\EditParentProfile;
use App\Filament\Parent\Pages\ParentLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The parents' portal: parents sign in with their phone number and follow
 * their children's results, report cards, fees, attendance and circulars.
 */
class ParentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('parent')
            ->path('parent')
            ->authGuard('parent')
            ->login(ParentLogin::class)
            ->profile(EditParentProfile::class, isSimple: false)
            ->brandName('Parents\' portal')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Parent/Resources'), for: 'App\Filament\Parent\Resources')
            ->discoverPages(in: app_path('Filament/Parent/Pages'), for: 'App\Filament\Parent\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Parent/Widgets'), for: 'App\Filament\Parent\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
            ]);
    }
}
