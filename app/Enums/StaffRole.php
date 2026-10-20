<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasLabel
{
    case Admin = 'admin';
    case HeadTeacher = 'head_teacher';
    case Teacher = 'teacher';
    case Bursar = 'bursar';
    case Secretary = 'secretary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'School administrator',
            self::HeadTeacher => 'Head teacher',
            self::Teacher => 'Teacher',
            self::Bursar => 'Bursar',
            self::Secretary => 'Secretary',
        };
    }
}
