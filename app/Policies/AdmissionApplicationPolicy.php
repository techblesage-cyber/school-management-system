<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\SchoolAbility;
use App\Models\AdmissionApplication;
use App\Models\User;

/**
 * The secretary records applications; the administrator and head teacher
 * decide on them (accept, not admitted, enrol).
 */
class AdmissionApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewAdmissions);
    }

    public function view(User $user, AdmissionApplication $application): bool
    {
        return $user->canInSchool(SchoolAbility::ViewAdmissions, $application->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageAdmissions);
    }

    public function update(User $user, AdmissionApplication $application): bool
    {
        return $application->status !== ApplicationStatus::Enrolled
            && $user->canInSchool(SchoolAbility::ManageAdmissions, $application->school_id);
    }

    public function delete(User $user, AdmissionApplication $application): bool
    {
        return $application->status !== ApplicationStatus::Enrolled
            && $user->canInSchool(SchoolAbility::ManageAdmissions, $application->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageAdmissions);
    }

    /**
     * Accept, mark as not admitted, or enrol.
     */
    public function decide(User $user, AdmissionApplication $application): bool
    {
        return $user->canInSchool(SchoolAbility::DecideAdmissions, $application->school_id);
    }
}
