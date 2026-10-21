<?php

namespace App\Filament\Shared;

use App\Models\ParentAccount;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Shown the first time someone signs in with a password the system made
 * for them (or after the school gave them a new one): they choose their
 * own, then carry on to their portal.
 */
class ChoosePassword extends SimplePage
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        if (! $this->user()?->must_change_password) {
            redirect()->to(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Choose your password';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Welcome, '.Str::of((string) $this->user()?->name)->trim()->explode(' ')->first().'!';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Choose your own password to use from now on. Pick something you will remember but others cannot guess.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->minLength(8)
                    ->same('passwordConfirmation')
                    ->helperText('At least 8 letters or numbers.')
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $user = $this->user();

                        if ($user && Hash::check((string) $value, $user->getAuthPassword())) {
                            $fail('Choose a different password from the one you were given.');
                        }

                        if ($user instanceof ParentAccount && self::usedAtAnotherSchool($user, (string) $value)) {
                            $fail('You use this password for another school\'s portal. Choose a different one, so each school\'s login stays separate.');
                        }
                    }),
                TextInput::make('passwordConfirmation')
                    ->label('Type it again')
                    ->password()
                    ->revealable()
                    ->autocomplete('new-password')
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function save(): void
    {
        $user = $this->user();
        abort_unless($user, 403);

        $data = $this->form->getState();

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        // Keep this sign-in going after the password change
        session()->put(['password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword()]);

        Notification::make()->title('Password saved. Welcome!')->success()->send();

        $this->redirect(Filament::getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save and continue')->submit('save'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    /**
     * A parent's accounts at other schools share their phone number; each
     * must keep its own password, or signing in couldn't tell them apart.
     */
    public static function usedAtAnotherSchool(ParentAccount $account, string $password): bool
    {
        return ParentAccount::query()
            ->where('phone', $account->getRawOriginal('phone'))
            ->whereKeyNot($account->getKey())
            ->get()
            ->contains(fn (ParentAccount $other): bool => Hash::check($password, $other->getAuthPassword()));
    }

    protected function user(): User|ParentAccount|null
    {
        $user = Filament::auth()->user();

        return $user instanceof User || $user instanceof ParentAccount ? $user : null;
    }
}
