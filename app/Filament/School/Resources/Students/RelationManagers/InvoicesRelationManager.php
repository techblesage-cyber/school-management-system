<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Filament\School\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Invoices" tab on a student (for staff who can see fees).
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('academicTerm'))
            ->recordTitleAttribute('number')
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice'),
                TextColumn::make('academicTerm.year')
                    ->label('Term')
                    ->formatStateUsing(fn (Invoice $record): ?string => $record->academicTerm?->name),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state)),
                TextColumn::make('issued_on')
                    ->label('Date')
                    ->date(),
            ])
            ->defaultSort('issued_on', 'desc')
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No invoices yet');
    }
}
