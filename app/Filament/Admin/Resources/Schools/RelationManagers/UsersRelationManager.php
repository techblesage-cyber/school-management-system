<?php

namespace App\Filament\Admin\Resources\Schools\RelationManagers;

use App\Filament\School\Pages\Staff;
use App\Services\Logins;
use Illuminate\Support\Str;
use App\Filament\Shared\InitialsAvatars;
use App\Enums\StaffRole;
use App\Filament\Components\PassportPhoto;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The "Staff" tab on a school: people who can log in to that school's portal.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Staff';

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    /** Whether the staff member being added gets an emailed first password */
    protected bool $makeLogin = false;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(table: User::class, ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(8)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Leave empty to keep the current password.'
                        : 'Leave empty and the system makes a first password and emails it to them.'),
                self::roleSelect(),
                PassportPhoto::make('staff-photos'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('photo')
                    ->defaultImageUrl(fn ($record): string => InitialsAvatars::for((string) $record->name))
                    ->disk('public')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => StaffRole::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                TextColumn::make('created_at')
                    ->label('Added')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New staff member')
                    ->mutateDataUsing(function (array $data): array {
                        $this->makeLogin = blank($data['password'] ?? null);

                        return $this->makeLogin ? [...$data, 'password' => Str::random(40)] : $data;
                    })
                    ->after(function (User $record, array $data): void {
                        if ($this->makeLogin) {
                            $role = $data['role'] ?? null;
                            $role = $role instanceof StaffRole ? $role : StaffRole::tryFrom((string) $role);
                            $login = Logins::newStaffPassword($record, $this->getOwnerRecord(), $role?->getLabel());
                            Staff::loginNotice($record, $login, "Login for {$record->name}")->send();
                        }
                    }),
                AttachAction::make()
                    ->label('Add existing user')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        self::roleSelect(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Remove from school'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove from school'),
                ]),
            ]);
    }

    private static function roleSelect(): Select
    {
        return Select::make('role')
            ->options(StaffRole::class)
            ->default(StaffRole::Admin->value)
            ->required();
    }
}
