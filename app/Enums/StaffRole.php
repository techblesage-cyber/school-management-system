<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasDescription, HasLabel
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

    /**
     * What each role may do. Sharebility super admins may do everything.
     *
     * @return array<int, SchoolAbility>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => SchoolAbility::cases(),
            self::HeadTeacher => [
                SchoolAbility::ViewStudents,
                SchoolAbility::ManageStudents,
                SchoolAbility::DeleteStudents,
                SchoolAbility::ManageClasses,
                SchoolAbility::ViewAdmissions,
                SchoolAbility::ManageAdmissions,
                SchoolAbility::DecideAdmissions,
                SchoolAbility::EditSchoolProfile,
            ],
            self::Secretary => [
                SchoolAbility::ViewStudents,
                SchoolAbility::ManageStudents,
                SchoolAbility::ViewAdmissions,
                SchoolAbility::ManageAdmissions,
            ],
            self::Teacher, self::Bursar => [
                SchoolAbility::ViewStudents,
            ],
        };
    }

    public function can(SchoolAbility $ability): bool
    {
        return in_array($ability, $this->abilities(), true);
    }

    /**
     * One line describing the role, shown when choosing a role for someone.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including adding and removing staff.',
            self::HeadTeacher => 'Students, classes, admissions decisions and the school profile.',
            self::Secretary => 'Adds and updates students, parents and applications.',
            self::Teacher, self::Bursar => 'Can view students and parents. More access comes with later modules.',
        };
    }
}
