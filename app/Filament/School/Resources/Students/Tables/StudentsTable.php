<?php

namespace App\Filament\School\Resources\Students\Tables;

use App\Filament\Shared\InitialsAvatars;
use App\Enums\Gender;
use App\Enums\Residence;
use App\Enums\StudentStatus;
use App\Models\SchoolClass;
use App\Models\Student;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // On phones the admission number and class sit under the name
            ->extraAttributes(['class' => 'details-on-phone'])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['schoolClass', 'stream']))
            ->columns([
                ImageColumn::make('photo')
                    ->defaultImageUrl(fn ($record): string => InitialsAvatars::for((string) $record->full_name))
                    ->disk('public')
                    ->circular()
                    ->toggleable(),
                TextColumn::make('admission_number')
                    ->label('Adm. no.')
                    ->searchable()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): string => collect([$record->admission_number, $record->class_and_stream])->filter()->implode(' · '))
                    ->weight('bold')
                    ->searchable(['first_name', 'last_name', 'other_names'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('class_and_stream')
                    ->label('Class')
                    ->visibleFrom('md'),
                TextColumn::make('gender')
                    ->visibleFrom('lg'),
                TextColumn::make('residence')
                    ->toggleable()
                    ->visibleFrom('lg'),
                TextColumn::make('status')
                    ->badge()
                    ->visibleFrom('md'),
                TextColumn::make('lin')
                    ->label('LIN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('admission_date')
                    ->label('Admitted')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name')
            ->filters([
                SelectFilter::make('school_class_id')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_id', Filament::getTenant()?->getKey())
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('gender')
                    ->options(Gender::class),
                SelectFilter::make('residence')
                    ->options(Residence::class),
                SelectFilter::make('status')
                    ->options(StudentStatus::class)
                    ->default(StudentStatus::Active->value),
                Filter::make('without_photo')
                    ->label('Without passport photo')
                    ->query(fn (Builder $query): Builder => $query->whereNull('photo')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('No students yet')
            ->emptyStateDescription('Add students here, or enrol them from accepted admission applications.');
    }
}
