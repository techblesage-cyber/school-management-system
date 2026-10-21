<?php

namespace App\Filament\Shared;

use App\Support\Apps;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * The look shared by all portals: the glass styles, phone "app" settings
 * and, on phones and tablets, the tab bar along the bottom.
 */
class Theme
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): View => view('filament.hooks.head'))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): string => self::tabBar());
    }

    /**
     * Home, the person's three main apps, and "More" (opens the full menu).
     */
    protected static function tabBar(): string
    {
        // Not on sign-in and other single-card pages
        if (! Filament::auth()->check() || str_contains((string) request()->route()?->getName(), '.auth.')) {
            return '';
        }

        try {
            $items = Filament::getCurrentPanel()?->getId() === 'school'
                ? collect(Apps::forSchool())->take(3)->map(fn (array $app): array => [
                    'label' => $app['label'], 'icon' => $app['icon'], 'url' => $app['url'],
                ])->all()
                : self::navigationItems();
        } catch (Throwable $exception) {
            report($exception);

            return '';
        }

        return view('filament.hooks.tab-bar', [
            'home' => Filament::getUrl(),
            'items' => $items,
            'current' => '/'.trim(request()->path(), '/'),
        ])->render();
    }

    /**
     * @return array<int, array{label: string, icon: mixed, url: string}>
     */
    protected static function navigationItems(): array
    {
        return collect(Filament::getNavigation())
            ->flatMap(fn ($group) => $group->getItems())
            ->filter(fn (NavigationItem $item): bool => $item->isVisible() && filled($item->getUrl()) && rtrim((string) $item->getUrl(), '/') !== rtrim(Filament::getUrl(), '/'))
            ->take(3)
            ->map(fn (NavigationItem $item): array => [
                'label' => (string) $item->getLabel(),
                'icon' => $item->getIcon() ?? Heroicon::OutlinedSquares2x2,
                'url' => (string) $item->getUrl(),
            ])
            ->values()
            ->all();
    }
}
