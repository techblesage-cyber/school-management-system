<?php

namespace App\Support\Results;

use App\Enums\StaffRole;
use App\Models\AcademicTerm;
use App\Models\Stream;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Renders printable report cards (one or a whole class) for staff and for
 * parents, from a class's term results.
 */
class ReportCardPrinter
{
    /**
     * @param  Collection<int, StudentResult>  $students
     */
    public static function render(TermResults $results, Collection $students): View
    {
        $term = $results->term;
        $class = $results->class;
        $school = $class->school()->first();

        $headTeacher = User::query()
            ->whereHas('schools', fn ($query) => $query
                ->whereKey($school->getKey())
                ->where('school_user.role', StaffRole::HeadTeacher->value))
            ->orderBy('id')
            ->value('name');

        $streamTeachers = Stream::query()->with('classTeacher')->where('school_class_id', $class->getKey())->get()->keyBy('id');
        $classTeacher = $class->classTeacher()->value('name');

        $cards = $students->values()->map(fn (StudentResult $result): array => [
            'result' => $result,
            'classTeacher' => ($result->streamId ? $streamTeachers->get($result->streamId)?->classTeacher?->name : null) ?? $classTeacher,
            'feesBalance' => $school->report_shows_fees ? $result->student->feeBalance() : null,
            'attendance' => static::attendance($result, $term),
        ]);

        return view('reports.report-cards', [
            'school' => $school,
            'term' => $term,
            'nextTerm' => $term->next(),
            'results' => $results,
            'cards' => $cards,
            'headTeacher' => $headTeacher,
            'showPositions' => (bool) $school->show_positions,
        ]);
    }

    /**
     * Days present and absent in the term, when attendance is taken.
     *
     * @return array{present: int, absent: int, late: int}|null
     */
    protected static function attendance(StudentResult $result, AcademicTerm $term): ?array
    {
        if (! class_exists(\App\Models\AttendanceRecord::class)) {
            return null;
        }

        return \App\Models\AttendanceRecord::summaryFor($result->student, $term);
    }
}
