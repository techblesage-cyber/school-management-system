<?php

namespace App\Filament\School\Widgets;

use App\Models\School;
use App\Models\User;
use App\Support\Apps;
use App\Support\Greeting;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * The top of the dashboard: a friendly greeting, then the person's apps as
 * big tiles, like a phone's home screen.
 */
class HomeScreen extends Widget
{
    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.home-screen';

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        /** @var School $school */
        $school = Filament::getTenant();

        return [
            'greeting' => Greeting::for($user->name),
            'subtitle' => collect([$user->isSuperAdmin() && ! $user->roleAt($school) ? 'Support' : $user->roleAt($school)?->getLabel(), $school->name])->filter()->implode(' · '),
            'date' => now()->isoFormat('dddd D MMMM'),
            'avatar' => $user->getFilamentAvatarUrl(),
            'initials' => Greeting::initials($user->name),
            'apps' => Apps::forSchool(),
        ];
    }
}
