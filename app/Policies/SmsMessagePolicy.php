<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\SmsMessage;
use App\Models\User;

/**
 * The SMS log is for the administrator and head teacher (Sharebility super
 * admins see every school's). Messages are never edited or deleted.
 */
class SmsMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::EditSchoolProfile);
    }

    public function view(User $user, SmsMessage $sms): bool
    {
        return $user->canInSchool(SchoolAbility::EditSchoolProfile, $sms->school_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SmsMessage $sms): bool
    {
        return false;
    }

    public function delete(User $user, SmsMessage $sms): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
