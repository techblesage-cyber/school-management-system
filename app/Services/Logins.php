<?php

namespace App\Services;

use App\Models\Guardian;
use App\Models\ParentAccount;
use App\Models\School;
use App\Models\User;
use App\Notifications\ParentLogin;
use App\Notifications\StaffLogin;
use App\Services\Sms\Sms;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Makes first logins for new staff and parents and sends them: by email,
 * and for parents also by SMS when the school uses SMS. Whoever made the
 * login is shown the password too, in case it doesn't arrive (or the person
 * has no email), so they can hand it over or print a login slip.
 */
class Logins
{
    /**
     * A first (or new) password for a staff member, emailed to them.
     *
     * @return array{password: string, emailed: bool}
     */
    public static function newStaffPassword(User $user, School $school, ?string $role = null, bool $isNewPassword = false): array
    {
        $password = ParentAccount::temporaryPassword();
        $user->forceFill(['password' => $password, 'must_change_password' => true])->save();

        return ['password' => $password, 'emailed' => self::mail($user, new StaffLogin($school, $password, $role, $isNewPassword))];
    }

    /**
     * Tells someone who already has an account that they've been added to
     * another school (they keep their password).
     */
    public static function addedToSchool(User $user, School $school, ?string $role = null): bool
    {
        return self::mail($user, new StaffLogin($school, null, $role));
    }

    /**
     * Gives a parent record its login to the school's parents' portal and
     * sends it. Nothing happens for a parent without a phone number.
     *
     * @return array{account: ParentAccount, password: string|null, sent: array<int, string>}|null
     */
    public static function parent(Guardian $guardian, bool $reset = false): ?array
    {
        if (blank($guardian->phone)) {
            return null;
        }

        [$account, $password] = ParentAccount::grantTo($guardian, resetPassword: $reset);
        $sent = [];

        if ($password) {
            /** @var School $school */
            $school = $guardian->school()->first();

            if (filled($account->email) && self::mail($account, new ParentLogin($school, $account->phone, $password))) {
                $sent[] = 'email';
            }

            if (Sms::portalLogin($guardian, $account, $password)) {
                $sent[] = 'SMS';
            }

            session()->put('login_slips.'.$guardian->getKey(), ['password' => $password, 'at' => now()->timestamp]);
        }

        return ['account' => $account, 'password' => $password, 'sent' => $sent];
    }

    /**
     * Sends the email, or notes in the log why it couldn't be sent, so a
     * mail problem never stops someone being added.
     */
    protected static function mail(User|ParentAccount $person, object $notification): bool
    {
        if (blank($person->email)) {
            return false;
        }

        try {
            $person->notify($notification);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Could not email a login', ['to' => $person->email, 'error' => $exception->getMessage()]);

            return false;
        }
    }
}
