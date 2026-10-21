<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Guardian;
use App\Models\User;

/**
 * Parents follow the same rules as students: everyone at the school can
 * see them, and the staff who manage students can change them.
 */
class GuardianPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents, $guardian->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageStudents);
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->canInSchool(SchoolAbility::ManageStudents, $guardian->school_id);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->canInSchool(SchoolAbility::DeleteStudents, $guardian->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::DeleteStudents);
    }
}
