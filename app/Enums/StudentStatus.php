<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StudentStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Graduated = 'graduated';
    case Transferred = 'transferred';
    case Left = 'left';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Graduated => 'Completed / graduated',
            self::Transferred => 'Transferred out',
            self::Left => 'Left school',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'danger',
            self::Graduated => 'info',
            self::Transferred, self::Left => 'gray',
        };
    }
}
