<?php

namespace App\Filament\School\Resources\AdmissionApplications\Tables;

use App\Enums\ApplicationStatus;
use App\Filament\School\Resources\AdmissionApplications\Actions\ApplicationActions;
use App\Models\AdmissionApplication;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdmissionApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('schoolClass'))
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->label('Applicant')
                    ->formatStateUsing(fn (AdmissionApplication $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'other_names']),
                TextColumn::make('schoolClass.name')
                    ->label('Class applied for')
                    ->placeholder('Not given'),
                TextColumn::make('guardian_name')
                    ->label('Parent')
                    ->description(fn (AdmissionApplication $record): string => $record->guardian_phone)
                    ->searchable(['guardian_name', 'guardian_phone']),
                TextColumn::make('applied_on')
                    ->label('Received')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->defaultSort('applied_on', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ApplicationStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    ...ApplicationActions::all(),
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->emptyStateHeading('No applications yet')
            ->emptyStateDescription('Record applications as they come in, then accept and enrol applicants from here.');
    }
}
