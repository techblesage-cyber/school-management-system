<?php

namespace App\Support;

use App\Models\AcademicTerm;
use App\Models\AttendanceRecord;
use App\Models\ResultPublication;
use App\Models\Student;
use App\Support\Results\StudentResult;
use App\Support\Results\TermResults;
use Illuminate\Support\Collection;

/**
 * What a parent may see about a child. Report cards appear only once the
 * school has published that class's results for the term.
 */
class ParentPortal
{
    /** @var array<string, StudentResult|null> */
    private static array $results = [];

    /**
     * Terms with published results for this child, newest first.
     *
     * @return Collection<int, AcademicTerm>
     */
    public static function publishedTerms(Student $student): Collection
    {
        return AcademicTerm::query()->withoutGlobalScopes()
            ->where('school_id', $student->school_id)
            ->whereIn('id', ResultPublication::query()->withoutGlobalScopes()->where('school_id', $student->school_id)->select('academic_term_id'))
            ->orderByDesc('year')
            ->orderByDesc('term')
            ->get()
            ->filter(fn (AcademicTerm $term): bool => static::isPublished($student, $term))
            ->values();
    }

    public static function isPublished(Student $student, AcademicTerm $term): bool
    {
        return $term->school_id === $student->school_id
            && ResultPublication::isPublished($term->getKey(), TermResults::classOfStudentInTerm($term, $student));
    }

    /**
     * The child's published result for a term, or null.
     */
    public static function result(Student $student, AcademicTerm $term): ?StudentResult
    {
        $key = $student->getKey().'-'.$term->getKey();

        if (! array_key_exists($key, static::$results)) {
            $result = static::isPublished($student, $term) ? TermResults::forStudent($term, $student) : null;
            static::$results[$key] = $result?->hasMarks() ? $result : null;
        }

        return static::$results[$key];
    }

    /**
     * The most recent published result.
     *
     * @return array{0: AcademicTerm, 1: StudentResult}|null
     */
    public static function latest(Student $student): ?array
    {
        foreach (static::publishedTerms($student) as $term) {
            if ($result = static::result($student, $term)) {
                return [$term, $result];
            }
        }

        return null;
    }

    /**
     * Average per published term, oldest first: ["Term 1, 2026" => 68.5, ...]
     *
     * @return array<string, float>
     */
    public static function averages(Student $student): array
    {
        return static::publishedTerms($student)
            ->reverse()
            ->mapWithKeys(fn (AcademicTerm $term): array => [$term->name => static::result($student, $term)?->average])
            ->filter(fn (?float $average): bool => $average !== null)
            ->all();
    }

    /**
     * Days present and absent this term.
     *
     * @return array{present: int, absent: int, late: int}|null
     */
    public static function attendanceThisTerm(Student $student): ?array
    {
        $term = AcademicTerm::currentFor($student->school_id);

        return $term ? AttendanceRecord::summaryFor($student, $term) : null;
    }

    /**
     * For tests: forget results worked out earlier.
     */
    public static function flush(): void
    {
        static::$results = [];
    }
}
