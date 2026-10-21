<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's attendance on one day.
 */
#[Fillable(['school_id', 'student_id', 'academic_term_id', 'school_class_id', 'stream_id', 'date', 'status', 'note', 'recorded_by'])]
class AttendanceRecord extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Records a student's attendance for a day, or clears it (status null).
     */
    public static function record(Student $student, string $date, ?AttendanceStatus $status, ?string $note = null, ?User $by = null): ?self
    {
        $day = fn () => static::query()->withoutGlobalScopes()
            ->where('student_id', $student->getKey())
            ->whereDate('date', $date);

        if ($status === null) {
            $day()->delete();

            return null;
        }

        $existing = $day()->first();

        $values = [
            'school_id' => $student->school_id,
            'academic_term_id' => AcademicTerm::currentFor($student->school_id)?->getKey(),
            'school_class_id' => $student->school_class_id,
            'stream_id' => $student->stream_id,
            'status' => $status,
            'note' => $note ?? $existing?->note,
            'recorded_by' => ($by ?? auth()->user())?->getKey(),
        ];

        if ($existing) {
            $existing->update($values);

            return $existing;
        }

        return static::query()->withoutGlobalScopes()->create(['student_id' => $student->getKey(), 'date' => $date, ...$values]);
    }

    /**
     * Days present (including late), absent and late in a term.
     *
     * @return array{present: int, absent: int, late: int}|null Null when no register was taken
     */
    public static function summaryFor(Student $student, AcademicTerm $term): ?array
    {
        $counts = static::query()->withoutGlobalScopes()
            ->where('student_id', $student->getKey())
            ->where('academic_term_id', $term->getKey())
            ->selectRaw('status, count(*) as days')
            ->groupBy('status')
            ->pluck('days', 'status');

        if ($counts->isEmpty()) {
            return null;
        }

        $days = fn (AttendanceStatus $status): int => (int) ($counts[$status->value] ?? 0);

        return [
            'present' => $days(AttendanceStatus::Present) + $days(AttendanceStatus::Late),
            'absent' => $days(AttendanceStatus::Absent) + $days(AttendanceStatus::Sick) + $days(AttendanceStatus::Excused),
            'late' => $days(AttendanceStatus::Late),
        ];
    }
}
