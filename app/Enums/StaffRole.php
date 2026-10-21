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
                SchoolAbility::ViewFees,
                SchoolAbility::EnterMarks,
                SchoolAbility::ManageExams,
                SchoolAbility::ViewResults,
                SchoolAbility::TakeAttendance,
                SchoolAbility::ManageCirculars,
                SchoolAbility::ManageParentAccess,
            ],
            self::Secretary => [
                SchoolAbility::ViewStudents,
                SchoolAbility::ManageStudents,
                SchoolAbility::ViewAdmissions,
                SchoolAbility::ManageAdmissions,
                SchoolAbility::ManageCirculars,
                SchoolAbility::ManageParentAccess,
            ],
            self::Bursar => [
                SchoolAbility::ViewStudents,
                SchoolAbility::ViewFees,
                SchoolAbility::ManageFees,
                SchoolAbility::RecordPayments,
            ],
            // Teachers enter marks, see results and take attendance only for
            // the classes they teach or are class teacher of
            self::Teacher => [
                SchoolAbility::ViewStudents,
                SchoolAbility::EnterMarks,
                SchoolAbility::ViewResults,
                SchoolAbility::TakeAttendance,
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
            self::HeadTeacher => 'Students, classes, exams and report cards, admissions, circulars and the school profile. Can see fees.',
            self::Secretary => 'Adds and updates students, parents and applications. Sends circulars and gives parents portal access.',
            self::Bursar => 'Fees: fee structure, bills, payments and receipts. Can view students and parents.',
            self::Teacher => 'Enters marks and takes attendance for their own subjects and classes. Writes class teacher comments.',
        };
    }
}
