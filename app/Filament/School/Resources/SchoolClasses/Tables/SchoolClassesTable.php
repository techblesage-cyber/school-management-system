<?php

namespace App\Filament\School\Resources\SchoolClasses\Tables;

use App\Models\SchoolClass;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SchoolClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Class')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('level')
                    ->badge(),
                TextColumn::make('streams.name')
                    ->label('Streams')
                    ->badge()
                    ->color('gray')
                    ->placeholder('No streams'),
                TextColumn::make('active_students_count')
                    ->label('Students')
                    ->counts(['students as active_students_count' => fn (Builder $query) => $query->where('status', 'active')]),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order')->orderBy('name'))
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (SchoolClass $record): bool => $record->students()->exists()),
            ])
            ->emptyStateHeading('No classes yet')
            ->emptyStateDescription('Use "Add standard classes" to create P.1–P.7, S.1–S.6 or nursery classes in one step.');
    }
}
