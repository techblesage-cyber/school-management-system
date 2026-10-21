<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasColor, HasLabel
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';

    public function getLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::Excused => 'Absent with permission',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Present => 'success',
            self::Late => 'warning',
            self::Absent => 'danger',
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
