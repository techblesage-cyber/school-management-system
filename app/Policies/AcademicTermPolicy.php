<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\AcademicTerm;
use App\Models\User;

/**
 * Terms are set up by the administrator or head teacher, or by the bursar
 * (who needs them for fees). Everyone at the school can see them.
 */
class AcademicTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents);
    }

    public function view(User $user, AcademicTerm $term): bool
    {
        return $user->canInSchool(SchoolAbility::ViewStudents, $term->school_id);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $this->manages($user, $term->school_id);
    }

    public function delete(User $user, AcademicTerm $term): bool
    {
        return $this->manages($user, $term->school_id) && ! $term->invoices()->withoutGlobalScopes()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function manages(User $user, ?int $schoolId = null): bool
    {
        return $user->canInSchool(SchoolAbility::ManageClasses, $schoolId)
            || $user->canInSchool(SchoolAbility::ManageFees, $schoolId);
    }
}
