<?php

namespace App\Filament\School\Resources\Guardians\Actions;

use App\Enums\SchoolAbility;
use App\Models\Guardian;
use App\Models\User;
use App\Services\Logins;
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
            ->modalDescription(fn (Guardian $record): string => 'They will sign in with '.PhoneNumber::display($record->phone).' and a first password, sent to them by email or SMS where possible, with a login slip you can print. They can then follow their children\'s results, report cards, fees and circulars.')
            ->modalSubmitActionLabel('Give access')
            ->action(fn (Guardian $record) => self::grant($record, reset: false));
    }

    public static function resetPassword(): Action
    {
        return Action::make('resetPortalPassword')
            ->label('Send new portal login')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Guardian $record): bool => self::allowed($record) && $record->parent_account_id !== null)
            ->requiresConfirmation()
            ->modalDescription('Makes them a new first password and sends it. Their old one stops working.')
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

    /**
     * Called when a parent is added: makes and sends their login straight
     * away, when the person adding them may give portal access.
     */
    public static function welcome(?Guardian $record): void
    {
        if ($record && self::allowed($record) && $record->parent_account_id === null) {
            self::notice($record, Logins::parent($record))?->send();
        }
    }

    protected static function grant(Guardian $record, bool $reset): void
    {
        abort_unless(self::allowed($record), 403);

        self::notice($record, Logins::parent($record, reset: $reset))?->send();
    }

    /**
     * What staff see after a parent's login is made: how it was sent, the
     * first password (in case it doesn't arrive) and a button to print a
     * login slip to hand over.
     *
     * @param  array{account: \App\Models\ParentAccount, password: string|null, sent: array<int, string>}|null  $result
     */
    public static function notice(Guardian $record, ?array $result): ?Notification
    {
        if (! $result) {
            return null;
        }

        if (! $result['password']) {
            return Notification::make()
                ->success()
                ->title("{$record->name} now has portal access")
                ->body('They already have a login for this school\'s portal with the same phone number, so they keep their password.');
        }

        $sent = $result['sent'] === []
            ? 'They have no email address and SMS is off, so print the login slip and give it to them.'
            : 'Sent to them by '.implode(' and ', $result['sent']).'.';

        return Notification::make()
            ->success()
            ->persistent()
            ->title("Parents' portal login for {$record->name}")
            ->body('Phone: '.PhoneNumber::display($result['account']->phone).' · First password: '.$result['password'].'. '.$sent.' They choose their own password when they first sign in.')
            ->actions([
                Action::make('slip')
                    ->label('Print login slip')
                    ->button()
                    ->url(route('parents.login-slip', $record, absolute: false), shouldOpenInNewTab: true),
            ]);
    }

    protected static function allowed(Guardian $record): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && filled($record->phone)
            && $user->canInSchool(SchoolAbility::ManageParentAccess, $record->school_id);
    }
}
