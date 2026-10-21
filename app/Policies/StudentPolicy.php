<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Student;
use App\Models\User;

/**
 * Who may see and change student records. Super admins pass the role
 * checks (see User::canInSchool) but not business rules, such as never
 * deleting a student with fees records. Record checks use the student's
 * own school.
 */
class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents, $student->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageStudents);
    }

    public function update(User $user, Student $student): bool
    {
        return $user->canInSchool(SchoolAbility::ManageStudents, $student->school_id);
    }

    /**
     * Students with fees records can't be deleted (their payments must stay
     * on record). Change their status to "Left school" instead.
     */
    public function delete(User $user, Student $student): bool
    {
        return $user->canInSchool(SchoolAbility::DeleteStudents, $student->school_id)
            && ! $student->hasFeeRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::DeleteStudents);
    }
}
