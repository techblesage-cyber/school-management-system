<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ClassLevel: string implements HasLabel
{
    case Nursery = 'nursery';
    case Primary = 'primary';
    case OLevel = 'o_level';
    case ALevel = 'a_level';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nursery => 'Nursery',
            self::Primary => 'Primary',
            self::OLevel => 'O-level',
            self::ALevel => 'A-level',
            self::Other => 'Other',
        };
    }

    /**
     * Where this level's classes sit in lists: nursery first, A-level last.
     */
    public function sortBase(): int
    {
        return match ($this) {
            self::Nursery => 0,
            self::Primary => 10,
            self::OLevel => 20,
            self::ALevel => 30,
            self::Other => 40,
        };
    }

    /**
     * The usual Ugandan classes for this level, offered as a quick setup.
     *
     * @return array<int, string>
     */
    public function standardClasses(): array
    {
        return match ($this) {
            self::Nursery => ['Baby Class', 'Middle Class', 'Top Class'],
            self::Primary => ['P.1', 'P.2', 'P.3', 'P.4', 'P.5', 'P.6', 'P.7'],
            self::OLevel => ['S.1', 'S.2', 'S.3', 'S.4'],
            self::ALevel => ['S.5', 'S.6'],
            self::Other => [],
        };
    }
}
