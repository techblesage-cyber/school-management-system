<?php

namespace App\Filament\School\Resources\Guardians\RelationManagers;

use App\Enums\GuardianRelationship;
use App\Filament\School\Resources\Students\StudentResource;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Children" tab on a parent: the students this parent is linked to.
 */
class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Children';

    protected static ?string $modelLabel = 'child';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::linkFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['schoolClass', 'stream']))
            ->recordTitle(fn (Student $record): string => "{$record->full_name} ({$record->admission_number})")
            ->columns([
                TextColumn::make('admission_number')
                    ->label('Adm. no.'),
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name),
                TextColumn::make('class_and_stream')
                    ->label('Class'),
                TextColumn::make('relationship')
                    ->formatStateUsing(fn (?string $state): string => GuardianRelationship::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                IconColumn::make('is_primary')
                    ->label('Main contact')
                    ->boolean(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Link a student')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['first_name', 'last_name', 'admission_number'])
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...self::linkFields(),
                    ]),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (Student $record): string => StudentResource::getUrl('edit', ['record' => $record])),
                EditAction::make()
                    ->label('Edit link'),
                DetachAction::make()
                    ->label('Unlink'),
            ]);
    }

    /**
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
