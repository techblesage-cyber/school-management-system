<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * Everyone at the school can see the classes; the administrator and head
 * teacher set them up.
 */
class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents, $class->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses);
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses, $class->school_id);
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses, $class->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses);
    }
}
