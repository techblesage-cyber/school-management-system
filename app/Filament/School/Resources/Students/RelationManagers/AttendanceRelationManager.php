<?php

namespace App\Filament\School\Resources\Students\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * "Attendance" tab on a student: the days marked in the register.
 */
class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    protected static ?string $title = 'Attendance';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Anyone who can see the student can see their attendance.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Filament::auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        /** @var Student $student */
        $student = $this->getOwnerRecord();
        $term = AcademicTerm::currentFor($student->school_id);
        $summary = $term ? AttendanceRecord::summaryFor($student, $term) : null;

        return $table
            ->heading('Attendance'.($summary ? " · {$term->name}: {$summary['present']} days present, {$summary['absent']} absent" : ''))
            ->columns([
                TextColumn::make('date')
                    ->date('D j M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('note')
                    ->placeholder('—'),
                TextColumn::make('recordedBy.name')
                    ->label('Marked by')
                    ->visibleFrom('md'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(AttendanceStatus::class),
                SelectFilter::make('academic_term_id')
                    ->label('Term')
                    ->options(fn (): array => AcademicTerm::optionsFor($student->school_id))
                    ->default($term?->getKey()),
            ])
            ->emptyStateHeading('No attendance marked yet');
    }
}
