<?php

namespace App\Filament\School\Resources\Subjects\Tables;

use App\Models\Subject;
use App\Models\TeachingAssignment;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['teachingAssignments.user']))
            ->columns([
                TextColumn::make('name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('code')
                    ->placeholder('—'),
                IconColumn::make('in_aggregate')
                    ->label('In aggregate')
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('teachers')
                    ->state(fn (Subject $record): array => $record->teachingAssignments
                        ->map(fn (TeachingAssignment $assignment): string => $assignment->user->name)
                        ->unique()
                        ->sort()
                        ->values()
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('No teacher yet'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Teachers will no longer be listed as teaching this subject.'),
            ])
            ->emptyStateHeading('No subjects yet')
            ->emptyStateDescription('Use "Add standard subjects" to add the usual subjects for your school in one step.');
    }
}
