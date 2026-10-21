<?php

namespace App\Filament\School\Resources\Invoices\Tables;

use App\Models\AcademicTerm;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['student.schoolClass', 'student.stream', 'academicTerm']))
            ->columns([
                TextColumn::make('number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Invoice $record): string => $record->student->full_name)
                    ->description(fn (Invoice $record): string => trim($record->student->admission_number.' · '.$record->student->class_and_stream, ' ·'))
                    ->searchable(['first_name', 'last_name', 'admission_number']),
                TextColumn::make('academicTerm.year')
                    ->label('Term')
                    ->formatStateUsing(fn (Invoice $record): ?string => $record->academicTerm?->name)
                    ->visibleFrom('lg'),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable()
                    ->summarize(Sum::make()->label('Total billed')->formatStateUsing(fn ($state): string => Money::format($state))),
                TextColumn::make('issued_on')
                    ->label('Date')
                    ->date()
                    ->sortable()
                    ->toggleable()
                    ->visibleFrom('md'),
            ])
            ->defaultSort('number', 'desc')
            ->filters([
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn (): array => AcademicTerm::optionsFor(Filament::getTenant()))
                    ->default(fn (): ?int => AcademicTerm::currentFor(Filament::getTenant())?->getKey()),
                SelectFilter::make('class')
                    ->label('Class')
                    ->options(fn (): array => SchoolClass::query()
                        ->where('school_id', Filament::getTenant()?->getKey())
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('student', fn (Builder $query) => $query->where('school_class_id', $data['value']))
                        : $query),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('No invoices for this term')
            ->emptyStateDescription('Set the fee structure, then use "Bill students" to create every student\'s invoice at once.');
    }
}
