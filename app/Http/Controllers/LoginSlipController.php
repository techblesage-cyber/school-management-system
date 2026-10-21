<?php

namespace App\Http\Controllers;

use App\Enums\SchoolAbility;
use App\Models\Guardian;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\SchoolAddress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A printed slip with a parent's first login to the parents' portal, for
 * parents without email (or SMS). Only shows a password that the person
 * printing it has just made, in this same sign-in.
 */
class LoginSlipController extends Controller
{
    public function __invoke(Request $request, Guardian $guardian): View
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canInSchool(SchoolAbility::ManageParentAccess, $guardian->school_id), 403);

        $slip = $request->session()->get('login_slips.'.$guardian->getKey());
        abort_unless(is_array($slip) && now()->timestamp - ($slip['at'] ?? 0) < 3600, 404, 'This login slip has expired. Give the parent a new login to print one.');

        $school = $guardian->school()->first();
        $children = $guardian->students()->withoutGlobalScopes()->get();

        return view('print.login-slip', [
            'school' => $school,
            'guardian' => $guardian,
            'children' => $children,
            'phone' => PhoneNumber::display($guardian->phone),
            'password' => $slip['password'],
            'address' => SchoolAddress::parentPortal($school),
        ]);
    }
}
