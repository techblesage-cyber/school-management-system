<?php

namespace App\Filament\School\Resources\Guardians\Tables;

use App\Models\Guardian;
use App\Models\Student;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuardiansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('students'))
            ->columns([
                ImageColumn::make('photo')
                    ->disk('public')
                    ->circular()
                    ->toggleable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('children')
                    ->state(fn (Guardian $record): array => $record->students->map(fn (Student $student): string => $student->full_name)->all())
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->placeholder('None linked'),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('occupation')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('without_photo')
                    ->label('Without passport photo')
                    ->query(fn (Builder $query): Builder => $query->whereNull('photo')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
