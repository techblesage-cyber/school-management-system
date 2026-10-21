<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\FeeItem;
use App\Models\User;

class FeeItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees);
    }

    public function view(User $user, FeeItem $item): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees, $item->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees);
    }

    public function update(User $user, FeeItem $item): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees, $item->school_id);
    }

    public function delete(User $user, FeeItem $item): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees, $item->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees);
    }
}
