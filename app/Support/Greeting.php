<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * "Good morning, Sarah" in the school's time of day.
 */
class Greeting
{
    public static function for(?string $name): string
    {
        $hour = (int) now()->format('G');
        $part = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $first = Str::of((string) $name)->trim()->explode(' ')->first();

        return filled($first) ? "{$part}, {$first}" : $part;
    }

    public static function initials(?string $name): string
    {
        return Str::of((string) $name)->trim()->explode(' ')->filter()->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }
}
