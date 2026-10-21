<?php

namespace App\Filament\Shared;

use App\Support\Greeting;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Round coloured avatars with a person's (or school's) initials, drawn here
 * instead of fetched from an outside website, so they always show, even on
 * a slow or no internet connection.
 */
class InitialsAvatars implements AvatarProvider
{
    private const COLOURS = ['#007aff', '#34c759', '#ff9500', '#af52de', '#ff2d55', '#5856d6', '#30b0c7', '#ff3b30', '#00c7be', '#a2845e'];

    public function get(Model|Authenticatable $record): string
    {
        return self::for((string) Filament::getNameForDefaultAvatar($record));
    }

    public static function for(string $name): string
    {
        $initials = e(Greeting::initials($name) ?: '?');
        $colour = self::COLOURS[abs(crc32($name)) % count(self::COLOURS)];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".28"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient></defs>'
            .'<rect width="64" height="64" fill="'.$colour.'"/><rect width="64" height="64" fill="url(#g)"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#fff" font-family="-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif" font-size="26" font-weight="700">'.$initials.'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
