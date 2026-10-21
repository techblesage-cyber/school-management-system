<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\GradingScale;
use App\Models\User;

/**
 * Grading scales are set up by the head teacher or administrator.
 */
class GradingScalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams);
    }

    public function view(User $user, GradingScale $scale): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $scale->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams);
    }

    public function update(User $user, GradingScale $scale): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $scale->school_id);
    }

    public function delete(User $user, GradingScale $scale): bool
    {
        return $user->canInSchool(SchoolAbility::ManageExams, $scale->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
