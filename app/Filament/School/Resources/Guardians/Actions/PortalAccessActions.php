<?php

namespace App\Filament\School\Resources\Guardians\Actions;

use App\Enums\SchoolAbility;
use App\Models\Guardian;
use App\Models\ParentAccount;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Giving parents a login to the parents' portal, resetting it, or
 * taking it away.
 */
class PortalAccessActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [self::give(), self::resetPassword(), self::remove()];
    }

    public static function give(): Action
    {
        return Action::make('givePortalAccess')
            ->label('Give portal access')
            ->icon(Heroicon::OutlinedKey)
            ->color('success')
            ->visible(fn (Guardian $record): bool => self::allowed($record) && $record->parent_account_id === null)
            ->requiresConfirmation()
            ->modalHeading(fn (Guardian $record): string => "Parents' portal for {$record->name}")
            ->modalDescription(fn (Guardian $record): string => 'They will sign in with '.PhoneNumber::display($record->phone).' and a password shown to you next. They can then follow their children\'s results, report cards, fees and circulars.')
            ->modalSubmitActionLabel('Give access')
            ->action(fn (Guardian $record) => self::grant($record, reset: false));
    }

    public static function resetPassword(): Action
    {
        return Action::make('resetPortalPassword')
            ->label('Reset portal password')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Guardian $record): bool => self::allowed($record) && $record->parent_account_id !== null)
            ->requiresConfirmation()
            ->modalDescription('Gives them a new password. Their old one stops working.')
            ->action(fn (Guardian $record) => self::grant($record, reset: true));
    }

    public static function remove(): Action
    {
        return Action::make('removePortalAccess')
            ->label('Remove portal access')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (Guardian $record): bool => self::allowed($record) && $record->parent_account_id !== null)
            ->requiresConfirmation()
            ->modalDescription('They will no longer see this school\'s children in the parents\' portal.')
            ->action(function (Guardian $record): void {
                $record->forceFill(['parent_account_id' => null])->save();

                Notification::make()->title("Portal access removed for {$record->name}")->success()->send();
            });
    }

    protected static function grant(Guardian $record, bool $reset): void
    {
        abort_unless(self::allowed($record), 403);

        [$account, $password] = ParentAccount::grantTo($record, resetPassword: $reset);

        $notification = Notification::make()->success()->persistent();

        if ($password) {
            $notification
                ->title("Portal login for {$record->name}")
                ->body('Phone: '.PhoneNumber::display($account->phone).' · Password: '.$password
                    .' · Sign in at '.url('/parent').'. Give these to the parent privately. They can change the password after signing in.');

            if (class_exists(\App\Services\Sms\Sms::class)) {
                \App\Services\Sms\Sms::portalLogin($record, $account, $password, $notification);
            }
        } else {
            $notification
                ->title("{$record->name} now has portal access")
                ->body('They already had a portal account (for a child at another school), so they keep their password and will see this school\'s children too.');
        }

        $notification->send();
    }

    protected static function allowed(Guardian $record): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && filled($record->phone)
            && $user->canInSchool(SchoolAbility::ManageParentAccess, $record->school_id);
    }
}
