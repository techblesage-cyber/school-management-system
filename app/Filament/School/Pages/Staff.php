<?php

namespace App\Filament\School\Pages;

use App\Filament\Shared\InitialsAvatars;
use App\Enums\SchoolAbility;
use App\Enums\StaffRole;
use App\Filament\Components\PassportPhoto;
use App\Models\School;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\Logins;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Lets a school's administrator add their own staff, with passport photos,
 * roles, and the subjects and classes each teacher teaches.
 */
class Staff extends Page implements HasTable
{
    use InteractsWithTable;

    /** @var array{password: string|null, emailed: bool}|null The login just made, for the message after adding someone */
    protected ?array $lastLogin = null;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Staff';

    protected static ?string $slug = 'staff';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canInSchool(SchoolAbility::ManageStaff);
    }

    public function getSubheading(): ?string
    {
        return 'Who can sign in to this school\'s portal, what each person can do, and what they teach.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->relationship(fn (): BelongsToMany => $this->school()->users())
            ->inverseRelationship('schools')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'teachingAssignments' => fn ($query) => $query
                    ->where('school_id', $this->school()->getKey())
                    ->with(['subject', 'schoolClass', 'stream']),
            ]))
            ->columns([
                ImageColumn::make('photo')
                    ->defaultImageUrl(fn ($record): string => InitialsAvatars::for((string) $record->name))
                    ->disk('public')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (User $record): ?string => $record->is(auth()->user()) ? 'You' : $record->email),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => StaffRole::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                TextColumn::make('teaches')
                    ->label('Teaches')
                    ->state(fn (User $record): array => self::teachingSummary($record->teachingAssignments))
                    ->listWithLineBreaks()
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->headerActions([
                $this->addStaffAction(),
            ])
            ->recordActions([
                $this->editStaffAction(),
                $this->sendLoginAction(),
                DetachAction::make()
                    ->label('Remove from school')
                    ->modalDescription('They will no longer be able to sign in to this school\'s portal, and their subjects here are cleared. Their account is kept.')
                    ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                    ->after(fn (User $record) => TeachingAssignment::syncFor($this->school(), $record, [])),
            ])
            ->emptyStateHeading('No staff yet');
    }

    /**
     * Adds a new person, or an existing account (for example a teacher who
     * also works at another school) found by email address.
     */
    protected function addStaffAction(): CreateAction
    {
        $isExisting = fn (Get $get): bool => self::accountExists($get('email'));

        return CreateAction::make()
            ->label('Add staff member')
            ->modalHeading('Add staff member')
            ->modalDescription('The system makes their first password and emails it to them with the address to sign in. They choose their own password the first time.')
            ->schema([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if ($this->school()->users()->where('email', self::cleanEmail($value))->exists()) {
                            $fail('This person is already on the staff list.');
                        }
                    }),
                TextInput::make('name')
                    ->label('Full name')
                    ->required(fn (Get $get): bool => ! $isExisting($get))
                    ->hidden($isExisting)
                    ->maxLength(255),
                PassportPhoto::make('staff-photos')
                    ->hidden($isExisting),
                self::roleField()
                    ->helperText(fn (Get $get): ?string => $isExisting($get)
                        ? 'This email already has an account, so they will use their existing password and photo.'
                        : null),
                $this->teachingField(),
            ])
            ->using(function (array $data): User {
                $school = $this->school();
                $email = self::cleanEmail($data['email']);

                $existing = User::where('email', $email)->first();
                $user = $existing ?? User::create([
                    'name' => $data['name'],
                    'email' => $email,
                    'password' => Str::random(40),
                    'photo' => $data['photo'] ?? null,
                ]);

                $role = self::roleFrom($data['role']);
                $school->users()->attach($user, ['role' => $role?->value]);
                TeachingAssignment::syncFor($school, $user, $data['teaching'] ?? []);

                // Their login, by email
                $this->lastLogin = $existing
                    ? ['password' => null, 'emailed' => Logins::addedToSchool($user, $school, $role?->getLabel())]
                    : Logins::newStaffPassword($user, $school, $role?->getLabel());

                return $user;
            })
            ->successNotification(fn (User $record): Notification => self::loginNotice($record, $this->lastLogin, "{$record->name} added"));
    }

    /**
     * A new first password, emailed to them, for someone who has forgotten
     * theirs or never received the first email.
     */
    protected function sendLoginAction(): Action
    {
        return Action::make('sendLogin')
            ->label('Send new login')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (User $record): bool => $this->managesAccount($record) && ! $record->is(auth()->user()))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record): string => "New login for {$record->name}?")
            ->modalDescription('Makes a new first password and emails it to them. Their old password stops working.')
            ->action(function (User $record): void {
                abort_unless($this->managesAccount($record) && ! $record->is(auth()->user()), 403);

                $login = Logins::newStaffPassword($record, $this->school(), isNewPassword: true);

                self::loginNotice($record, $login, "New login for {$record->name}")->send();
            });
    }

    /**
     * Tells whoever added the person how their login was sent, with the
     * first password in case the email doesn't arrive.
     *
     * @param  array{password: string|null, emailed: bool}|null  $login
     */
    public static function loginNotice(User $user, ?array $login, string $title): Notification
    {
        $notification = Notification::make()->success()->title($title);

        if (! ($login['password'] ?? null)) {
            return $notification->body(($login['emailed'] ?? false)
                ? "They keep their usual password. We emailed {$user->email} to let them know."
                : 'They keep their usual password and can sign in now.');
        }

        return $notification
            ->persistent()
            ->body(($login['emailed'] ? "Their login was emailed to {$user->email}." : 'The email could not be sent.')
                ." First password: {$login['password']} (in case the email doesn't arrive). They choose their own password when they first sign in.");
    }

    protected function editStaffAction(): EditAction
    {
        return EditAction::make()
            ->modalHeading(fn (User $record): string => "Edit {$record->name}")
            ->mutateRecordDataUsing(fn (array $data, User $record): array => [
                ...$data,
                'teaching' => TeachingAssignment::rowsFor($this->school(), $record),
            ])
            ->schema(fn (User $record): array => [
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->visible($this->managesAccount($record)),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(table: User::class, ignoreRecord: true)
                    ->visible($this->managesAccount($record)),
                PassportPhoto::make('staff-photos')
                    ->visible($this->managesAccount($record)),
                self::roleField()
                    ->disabled($record->is(auth()->user()))
                    ->helperText($record->is(auth()->user())
                        ? 'You can\'t change your own role. Ask another administrator.'
                        : ($this->managesAccount($record) ? null : 'This person also works at another school, so only their role and teaching here can be changed.')),
                $this->teachingField(),
            ])
            ->using(function (User $record, array $data): User {
                $school = $this->school();

                // Account details: only for people who work at this school only
                $account = Arr::only($data, ['name', 'email', 'photo']);

                if ($account !== [] && $this->managesAccount($record)) {
                    $record->update($account);
                }

                if (array_key_exists('role', $data) && ! $record->is(auth()->user())) {
                    $school->users()->updateExistingPivot($record->getKey(), ['role' => self::roleFrom($data['role'])?->value]);
                }

                TeachingAssignment::syncFor($school, $record, $data['teaching'] ?? []);

                return $record->forgetSchoolRoles();
            });
    }

    protected static function roleField(): Radio
    {
        return Radio::make('role')
            ->label('Role')
            ->options(StaffRole::class)
            ->default(StaffRole::Teacher->value)
            ->required()
            ->live();
    }

    /**
     * One row per subject, each with the classes (or streams) taught.
     * Teachers must have at least one subject; other roles may teach too.
     */
    protected function teachingField(): Repeater
    {
        $isTeacher = fn (Get $get): bool => self::roleFrom($get('role')) === StaffRole::Teacher;

        return Repeater::make('teaching')
            ->label('Subjects and classes taught')
            ->helperText(fn (): ?string => $this->school()->subjects()->exists()
                ? 'Add one row per subject. A teacher can have several subjects, each in several classes.'
                : 'No subjects yet. Add them under Setup → Subjects first.')
            ->schema([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(fn (): array => $this->school()->subjects()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->distinct(),
                Select::make('classes')
                    ->label('Classes / streams')
                    ->multiple()
                    ->options(fn (): array => TeachingAssignment::classOptions($this->school()))
                    ->searchable()
                    ->required(),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel('Add subject')
            ->reorderable(false)
            ->required($isTeacher)
            ->validationMessages(['required' => 'Add at least one subject this teacher teaches.']);
    }

    /**
     * "Mathematics: S.1, S.2 East" — one line per subject.
     *
     * @param  Collection<int, TeachingAssignment>  $assignments
     * @return array<int, string>
     */
    public static function teachingSummary(Collection $assignments): array
    {
        return $assignments
            ->groupBy(fn (TeachingAssignment $assignment): string => $assignment->subject->name)
            ->sortKeys()
            ->map(fn (Collection $rows, string $subject): string => $subject.': '.$rows->map->class_label->sort()->implode(', '))
            ->values()
            ->all();
    }

    /**
     * Name, email, password and photo can only be changed here for people who
     * work at this school only. Otherwise one school could take over an
     * account that another school relies on.
     */
    protected function managesAccount(User $user): bool
    {
        return ! $user->isSuperAdmin()
            && $user->schools()->whereKeyNot($this->school()->getKey())->doesntExist();
    }

    protected function school(): School
    {
        /** @var School */
        return Filament::getTenant();
    }

    /**
     * The role field gives back either the enum or its value.
     */
    protected static function roleFrom(mixed $role): ?StaffRole
    {
        return $role instanceof StaffRole ? $role : StaffRole::tryFrom((string) $role);
    }

    protected static function accountExists(?string $email): bool
    {
        return filled($email) && User::where('email', self::cleanEmail($email))->exists();
    }

    protected static function cleanEmail(mixed $email): string
    {
        return strtolower(trim((string) $email));
    }
}
