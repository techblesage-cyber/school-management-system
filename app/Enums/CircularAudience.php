<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CircularAudience: string implements HasLabel
{
    case Parents = 'parents';
    case Staff = 'staff';
    case Everyone = 'everyone';

    public function getLabel(): string
    {
        return match ($this) {
            self::Parents => 'Parents',
            self::Staff => 'Staff',
            self::Everyone => 'Parents and staff',
        };
    }

    public function includesParents(): bool
    {
        return $this !== self::Staff;
    }

    public function includesStaff(): bool
    {
        return $this !== self::Parents;
    }
}
