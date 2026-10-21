<?php

namespace App\Filament\Shared;

use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Each portal's first page. The greeting at the top of the page replaces
 * the usual page title.
 */
class Home extends Dashboard
{
    protected static ?string $title = 'Home';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
