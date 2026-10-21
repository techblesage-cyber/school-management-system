<?php

namespace App\Filament\Admin\Resources\Schools\Tables;

use App\Filament\Shared\InitialsAvatars;
use App\Models\School;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->defaultImageUrl(fn ($record): string => InitialsAvatars::for((string) $record->name))
                    ->disk('public')
                    ->circular(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Web address name')
                    ->searchable(),
                TextColumn::make('users_count')
                    ->label('Staff')
                    ->counts('users')
                    ->sortable(),
                TextColumn::make('phone')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                self::openPortalAction(),
                EditAction::make(),
            ]);
    }

    /**
     * Opens the school's own portal (the school panel) in a new tab.
     */
    public static function openPortalAction(): Action
    {
        return Action::make('openPortal')
            ->label('Open portal')
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->color('gray')
            ->url(fn (School $record): ?string => Filament::getPanel('school')->getUrl($record))
            ->openUrlInNewTab()
            ->visible(fn (School $record): bool => $record->is_active);
    }
}
