<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GuardianRelationship: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';
    case Grandparent = 'grandparent';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Sibling = 'sibling';
    case Sponsor = 'sponsor';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Father => 'Father',
            self::Mother => 'Mother',
            self::Guardian => 'Guardian',
            self::Grandparent => 'Grandparent',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Sibling => 'Brother / sister',
            self::Sponsor => 'Sponsor',
            self::Other => 'Other',
        };
    }
}
