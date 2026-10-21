<?php

namespace App\Filament\Parent\Pages;

use App\Models\ParentAccount;
use App\Support\PhoneNumber;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Parents sign in with the phone number the school has for them and the
 * password the school gave them.
 */
class ParentLogin extends Login
{
    public function getHeading(): string|Htmlable
    {
        return 'Parents\' portal';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Sign in with your phone number and the password from your child\'s school. Each school gives you its own password.';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label('Phone number')
            ->placeholder('e.g. 0772 123 456')
            ->tel()
            ->required()
            ->autocomplete('tel')
            ->autofocus();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        $account = Filament::auth()->user();

        if ($account instanceof ParentAccount) {
            $account->forceFill(['last_login_at' => now()])->saveQuietly();
        }

        return $response;
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'phone' => PhoneNumber::normalize($data['phone'] ?? '') ?? '',
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => 'That phone number and password don\'t match. Check with your child\'s school if you need a new password.',
        ]);
    }
}
