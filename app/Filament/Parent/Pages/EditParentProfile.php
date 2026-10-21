<?php

namespace App\Filament\Parent\Pages;

use App\Filament\Shared\ChoosePassword;
use App\Models\ParentAccount;
use App\Support\PhoneNumber;
use Closure;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Parents change their name, email and password. Their phone number is the
 * one the school has; the school changes it if needed.
 */
class EditParentProfile extends EditProfile
{
    public static function getLabel(): string
    {
        return 'My account';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Your details')
                    ->schema([
                        TextInput::make('phone_display')
                            ->label('Phone number (used to sign in)')
                            ->formatStateUsing(fn (): ?string => PhoneNumber::display($this->getUser()->getAttributeValue('phone')))
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('To change it, ask your child\'s school.'),
                        $this->getNameFormComponent(),
                        TextInput::make('email')
                            ->label('Email (optional)')
                            ->email()
                            ->maxLength(255),
                    ]),
                Section::make('Change password')
                    ->description('Choose your own password in place of the one from the school.')
                    ->schema([
                        $this->getPasswordFormComponent()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $account = $this->getUser();

                                if (filled($value) && $account instanceof ParentAccount && ChoosePassword::usedAtAnotherSchool($account, (string) $value)) {
                                    $fail('You use this password for another school\'s portal. Choose a different one, so each school\'s login stays separate.');
                                }
                            }),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (filled($data['password'] ?? null)) {
            $data['must_change_password'] = false;
        }

        return $data;
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')->email();
    }
}
