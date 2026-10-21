<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Absent = 'absent';
    case Sick = 'sick';
    case Late = 'late';
    case Excused = 'excused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Sick => 'Sick',
            self::Excused => 'Absent with permission',
        };
    }

    /**
     * The word on the register button.
     */
    public function buttonLabel(): string
    {
        return $this === self::Excused ? 'Permission' : $this->getLabel();
    }

    public function icon(): Heroicon
    {
        return match ($this) {
            self::Present => Heroicon::OutlinedCheckCircle,
            self::Absent => Heroicon::OutlinedXCircle,
            self::Sick => Heroicon::OutlinedHeart,
            self::Late => Heroicon::OutlinedClock,
            self::Excused => Heroicon::OutlinedDocumentCheck,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
            self::Sick => 'info',
            self::Excused => 'gray',
        };
    }

    /**
     * Late still counts as being at school.
     */
    public function isAtSchool(): bool
    {
        return in_array($this, [self::Present, self::Late], true);
    }
}
