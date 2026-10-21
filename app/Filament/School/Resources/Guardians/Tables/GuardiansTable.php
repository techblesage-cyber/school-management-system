<?php

namespace App\Filament\School\Resources\Guardians\Tables;

use App\Models\Guardian;
use App\Models\Student;
use App\Filament\School\Resources\Guardians\Actions\PortalAccessActions;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
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
                IconColumn::make('parent_account_id')
                    ->label('Portal')
                    ->tooltip('Has a parents\' portal login')
                    ->state(fn (Guardian $record): bool => $record->parent_account_id !== null)
                    ->boolean()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('without_photo')
                    ->label('Without passport photo')
                    ->query(fn (Builder $query): Builder => $query->whereNull('photo')),
                TernaryFilter::make('portal')
                    ->label('Parents\' portal')
                    ->trueLabel('Has a login')
                    ->falseLabel('No login yet')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('parent_account_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('parent_account_id'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                ActionGroup::make(PortalAccessActions::all()),
            ]);
    }
}
