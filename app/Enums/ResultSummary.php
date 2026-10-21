<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * How a student's overall result for the term is worked out.
 */
enum ResultSummary: string implements HasDescription, HasLabel
{
    case Average = 'average';
    case Aggregate = 'aggregate';
    case Points = 'points';

    public function getLabel(): string
    {
        return match ($this) {
            self::Average => 'Average mark',
            self::Aggregate => 'Aggregate and division',
            self::Points => 'Total points',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Average => 'Average of all subjects, with an overall grade. Used for O-level and lower primary.',
            self::Aggregate => 'Points of the best subjects added up (lower is better), then a division, as for PLE.',
            self::Points => 'Points of all subjects added up (higher is better), as for A-level.',
        };
    }
}
