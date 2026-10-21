<?php

namespace App\Filament\Parent\Widgets;

use App\Filament\Parent\Resources\Children\ChildResource;
use App\Filament\School\Pages\ReportCards;
use App\Models\Student;
use App\Support\Money;
use App\Support\ParentPortal;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The parent's dashboard: each child at a glance.
 */
class ChildrenOverview extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'My children';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => ChildResource::getEloquentQuery())
            ->columns([
                ImageColumn::make('photo')
                    ->disk('public')
                    ->circular()
                    ->visibleFrom('sm'),
                TextColumn::make('last_name')
                    ->label('Child')
                    ->formatStateUsing(fn (Student $record): string => $record->full_name)
                    ->description(fn (Student $record): string => trim($record->school->name.' · '.$record->class_and_stream, ' ·'))
                    ->weight('bold')
                    ->wrap(),
                TextColumn::make('latest_result')
                    ->label('Latest result')
                    ->state(fn (Student $record): ?string => ($latest = ParentPortal::latest($record)) ? ReportCards::summaryLine($latest[1]) : null)
                    ->description(fn (Student $record): ?string => ParentPortal::latest($record)[0]->name ?? null)
                    ->placeholder('No report card yet')
                    ->visibleFrom('md'),
                TextColumn::make('attendance')
                    ->label('This term')
                    ->state(fn (Student $record): ?string => ($a = ParentPortal::attendanceThisTerm($record)) ? "{$a['present']} days at school, {$a['absent']} absent" : null)
                    ->placeholder('—')
                    ->visibleFrom('lg'),
                TextColumn::make('fees')
                    ->label('Fees balance')
                    ->state(fn (Student $record): string => Money::balance($record->feeBalance()))
                    ->color(fn (Student $record): string => $record->feeBalance() > 0 ? 'danger' : 'success'),
            ])
            ->recordUrl(fn (Student $record): string => ChildResource::getUrl('view', ['record' => $record]))
            ->paginated(false);
    }
}
