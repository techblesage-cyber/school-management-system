<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Payment;
use App\Models\User;

/**
 * Payments are recorded by the bursar (or administrator), viewed by anyone
 * who can see fees, and never edited or deleted: only voided, by staff who
 * manage fees.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees, $payment->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::RecordPayments);
    }

    public function update(User $user, Payment $payment): bool
    {
        return false;
    }

    public function delete(User $user, Payment $payment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function void(User $user, Payment $payment): bool
    {
        return ! $payment->isVoided() && $user->canInSchool(SchoolAbility::ManageFees, $payment->school_id);
    }
}
