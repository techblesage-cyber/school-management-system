<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Assessment;
use App\Models\User;

/**
 * Exams are set up by the head teacher or administrator. Teachers choose
 * them on the "Enter marks" screen.
 */
class AssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $assessment->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $assessment->school_id);
    }

    /**
     * Exams with marks entered can't be deleted, so no teacher's work is lost.
     */
    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $assessment->school_id)
            && ! $assessment->marks()->exists();
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
