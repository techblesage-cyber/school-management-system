<?php

namespace App\Filament\School\Resources\Payments\Tables;

use App\Enums\PaymentMethod;
use App\Filament\School\Resources\Payments\Actions\PaymentActions;
use App\Models\AcademicTerm;
use App\Models\Payment;
use App\Support\Money;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['student.schoolClass', 'student.stream', 'receivedBy']))
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt')
                    ->searchable()
                    ->sortable()
                    ->badge(fn (Payment $record): bool => $record->isVoided())
                    ->color(fn (Payment $record): ?string => $record->isVoided() ? 'danger' : null)
                    ->description(fn (Payment $record): ?string => $record->isVoided() ? 'VOID: '.$record->void_reason : null)
                    ->visibleFrom('md'),
                TextColumn::make('student.last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Payment $record): string => $record->student->full_name)
                    ->description(fn (Payment $record): string => $record->isVoided()
                        ? 'VOID · '.$record->receipt_number
                        : trim($record->student->admission_number.' · '.$record->student->class_and_stream, ' ·'))
                    ->searchable(['first_name', 'last_name', 'admission_number']),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable()
                    ->summarize(Sum::make()
                        ->label('Total received')
                        ->query(fn (QueryBuilder $query): QueryBuilder => $query->whereNull('voided_at'))
                        ->formatStateUsing(fn ($state): string => Money::format($state))),
                TextColumn::make('method')
                    ->label('Paid by')
                    ->description(fn (Payment $record): ?string => $record->reference)
                    ->visibleFrom('lg'),
                TextColumn::make('paid_on')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('receivedBy.name')
                    ->label('Received by')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('paid_on', 'desc')
            ->filters([
                Filter::make('dates')
                    ->schema([
                        DatePicker::make('from')->label('Paid from'),
                        DatePicker::make('until')->label('Paid until'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_on', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('paid_on', '<=', $date))),
                SelectFilter::make('method')
                    ->label('Paid by')
                    ->options(PaymentMethod::class),
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn (): array => AcademicTerm::optionsFor(Filament::getTenant())),
                TernaryFilter::make('voided')
                    ->label('Voided payments')
                    ->placeholder('Hide voided')
                    ->trueLabel('Only voided')
                    ->falseLabel('Show all')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('voided_at'),
                        false: fn (Builder $query): Builder => $query,
                        blank: fn (Builder $query): Builder => $query->whereNull('voided_at'),
                    ),
            ])
            ->recordActions([
                PaymentActions::printReceipt(),
                ActionGroup::make([
                    ViewAction::make(),
                    PaymentActions::void(),
                ]),
            ])
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription('Record payments here, or from Fees → Balances.');
    }
}
