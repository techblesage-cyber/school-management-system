<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Subject;
use App\Models\User;

/**
 * Like classes: everyone at the school can see the subjects; the
 * administrator and head teacher set them up.
 */
class SubjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, Subject $subject): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents, $subject->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses);
    }

    public function update(User $user, Subject $subject): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses, $subject->school_id);
    }

    public function delete(User $user, Subject $subject): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses, $subject->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses);
    }
}
