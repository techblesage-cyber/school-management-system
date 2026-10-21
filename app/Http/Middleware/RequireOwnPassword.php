<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone signed in with a password the system made for them is sent to
 * "Choose your password" before anything else.
 */
class RequireOwnPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();
        $route = (string) $request->route()?->getName();

        if ($user?->must_change_password && $panel && ! str_ends_with($route, '.auth.choose-password') && ! str_ends_with($route, '.auth.logout')) {
            return redirect()->route("filament.{$panel->getId()}.auth.choose-password");
        }

        return $next($request);
    }
}
