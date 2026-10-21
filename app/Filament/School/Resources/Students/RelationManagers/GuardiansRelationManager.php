<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Filament\School\Resources\Guardians\Actions\PortalAccessActions;
use App\Filament\Shared\InitialsAvatars;
use App\Enums\GuardianRelationship;
use App\Filament\School\Resources\Guardians\Schemas\GuardianForm;
use App\Models\Guardian;
use App\Enums\SchoolAbility;
use App\Models\User;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Parents & guardians" tab on a student.
 */
class GuardiansRelationManager extends RelationManager
{
    protected static string $relationship = 'guardians';

    protected static ?string $title = 'Parents & guardians';

    protected static ?string $modelLabel = 'parent / guardian';

    /**
     * Only staff who may manage students can add, link or unlink here.
     */
    public function isReadOnly(): bool
    {
        $user = auth()->user();

        return parent::isReadOnly()
            || ! ($user instanceof User && $user->canInSchool(SchoolAbility::ManageStudents, $this->getOwnerRecord()->school_id));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...GuardianForm::fields(),
                ...self::linkFields(),
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
                TextColumn::make('name'),
                TextColumn::make('relationship')
                    ->formatStateUsing(fn (?string $state): string => GuardianRelationship::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                TextColumn::make('phone'),
                TextColumn::make('email')
                    ->toggleable(),
                IconColumn::make('is_primary')
                    ->label('Main contact')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New parent / guardian')
                    // A new parent gets their parents' portal login straight away
                    ->after(fn (Guardian $record) => PortalAccessActions::welcome($record)),
                AttachAction::make()
                    ->label('Link existing parent')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'phone'])
                    ->recordTitle(fn (Guardian $record): string => "{$record->name} ({$record->phone})")
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->helperText('Search by name or phone, e.g. a parent already registered for a brother or sister.'),
                        ...self::linkFields(),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make()
                    ->label('Unlink'),
            ])
            ->emptyStateHeading('No parent or guardian linked yet');
    }

    /**
     * How this parent is linked to this particular student.
     *
     * @return array<int, Select|Toggle>
     */
    private static function linkFields(): array
    {
        return [
            Select::make('relationship')
                ->label('Relationship to student')
                ->options(GuardianRelationship::class)
                ->default(GuardianRelationship::Guardian->value)
                ->required(),
            Toggle::make('is_primary')
                ->label('Main contact')
                ->default(true),
        ];
    }
}
