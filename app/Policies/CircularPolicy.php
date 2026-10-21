<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Circular;
use App\Models\User;

/**
 * The administrator, head teacher and secretary write circulars. Other staff
 * read the published circulars meant for staff.
 */
class CircularPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, Circular $circular): bool
    {
        if ($user->canInSchool(SchoolAbility::ManageCirculars, $circular->school_id)) {
            return true;
        }

        return $user->canInSchool(SchoolAbility::ViewStudents, $circular->school_id)
            && $circular->isPublished()
            && $circular->audience->includesStaff();
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageCirculars);
    }

    public function update(User $user, Circular $circular): bool
    {
        return $user->canInSchool(SchoolAbility::ManageCirculars, $circular->school_id);
    }

    public function delete(User $user, Circular $circular): bool
    {
        return $user->canInSchool(SchoolAbility::ManageCirculars, $circular->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageCirculars);
    }
}
