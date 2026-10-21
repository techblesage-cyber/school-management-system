<?php

namespace App\Policies;

use App\Enums\SchoolAbility;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->canInSchool(SchoolAbility::ViewFees, $invoice->school_id);
    }

    public function create(User $user): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees, $invoice->school_id);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->canInSchool(SchoolAbility::ManageFees, $invoice->school_id);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
