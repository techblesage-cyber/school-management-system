<?php

namespace App\Support\Results;

use App\Enums\ResultSummary;
use App\Enums\StudentStatus;
use App\Models\AcademicTerm;
use App\Models\Assessment;
use App\Models\GradeBand;
use App\Models\GradingScale;
use App\Models\Mark;
use App\Models\ReportComment;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use Illuminate\Support\Collection;

/**
 * Works out a class's results for a term from the marks entered: each
 * subject's final mark and grade, each student's average, aggregate and
 * division or total points, and positions in the class and stream.
 *
 * A subject's final mark combines the term's assessments by their weights:
 * with "Mid-term" weighted 30 and "End of term" weighted 70, a student with
 * 60% and 80% gets 74%. Assessments a student has no mark for are left out,
 * so a missed test doesn't count as zero.
 */
class TermResults
{
    /** @var Collection<int, Assessment> */
    public Collection $assessments;

    /** @var Collection<int, Subject> */
    public Collection $subjects;

    /** @var Collection<int, StudentResult> keyed by student id */
    public Collection $students;

    public ?GradingScale $scale;

    private function __construct(
        public readonly AcademicTerm $term,
        public readonly SchoolClass $class,
    ) {}

    public static function forClass(AcademicTerm $term, SchoolClass $class): self
    {
        $results = new self($term, $class);
        $results->calculate();

        return $results;
    }

    /**
     * One student's result for a term, worked out with their classmates so
     * positions are right. Uses the class they were in when marked.
     */
    public static function forStudent(AcademicTerm $term, Student $student): ?StudentResult
    {
        $classId = static::classOfStudentInTerm($term, $student);
        $class = $classId ? SchoolClass::query()->withoutGlobalScopes()->find($classId) : null;

        return $class ? static::forClass($term, $class)->students->get($student->getKey()) : null;
    }

    /**
     * The class a student was marked in during a term, or their current class.
     */
    public static function classOfStudentInTerm(AcademicTerm $term, Student $student): ?int
    {
        $marked = Mark::query()
            ->withoutGlobalScopes()
            ->where('student_id', $student->getKey())
            ->whereIn('assessment_id', Assessment::query()->withoutGlobalScopes()->where('academic_term_id', $term->getKey())->select('id'))
            ->whereNotNull('school_class_id')
            ->selectRaw('school_class_id, count(*) as marks_count')
            ->groupBy('school_class_id')
            ->orderByDesc('marks_count')
            ->value('school_class_id');

        return $marked ?? $student->school_class_id;
    }

    private function calculate(): void
    {
        $this->scale = $this->class->grading_scale_id
            ? GradingScale::query()->withoutGlobalScopes()->with('bands')->find($this->class->grading_scale_id)
            : null;

        $this->assessments = Assessment::query()
            ->withoutGlobalScopes()
            ->where('academic_term_id', $this->term->getKey())
            ->forClass($this->class->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $marks = Mark::query()
            ->withoutGlobalScopes()
            ->whereIn('assessment_id', $this->assessments->modelKeys())
            ->where('school_class_id', $this->class->getKey())
            ->get();

        // Students marked in this class this term, plus current members with no marks yet
        $studentIds = $marks->pluck('student_id')
            ->merge(Student::query()->withoutGlobalScopes()
                ->where('school_class_id', $this->class->getKey())
                ->where('status', StudentStatus::Active)
                ->pluck('id'))
            ->unique();

        $students = Student::query()->withoutGlobalScopes()
            ->with(['stream', 'schoolClass'])
            ->whereIn('id', $studentIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $this->subjects = Subject::query()->withoutGlobalScopes()
            ->whereIn('id', $marks->pluck('subject_id')->unique())
            ->orderBy('name')
            ->get();

        $teachers = $this->subjectTeachers();
        $comments = ReportComment::query()->withoutGlobalScopes()
            ->where('academic_term_id', $this->term->getKey())
            ->whereIn('student_id', $students->modelKeys())
            ->get()
            ->keyBy('student_id');

        $marksByStudent = $marks->groupBy('student_id');
        $streamNames = Stream::query()->where('school_class_id', $this->class->getKey())->pluck('name', 'id');

        $this->students = $students->mapWithKeys(function (Student $student) use ($marksByStudent, $teachers, $comments, $streamNames): array {
            $studentMarks = $marksByStudent->get($student->getKey(), collect());

            // The stream they were in when marked (or are in now)
            $streamId = $studentMarks->pluck('stream_id')->filter()->countBy()->sortDesc()->keys()->first() ?? $student->stream_id;

            $subjects = $studentMarks->groupBy('subject_id')
                ->map(fn (Collection $subjectMarks, int $subjectId): SubjectResult => $this->subjectResult(
                    $this->subjects->firstWhere('id', $subjectId),
                    $subjectMarks,
                    $teachers[$subjectId.'-'.($streamId ?? 0)] ?? $teachers[$subjectId.'-0'] ?? null,
                ))
                ->sortBy(fn (SubjectResult $result): string => $result->subject->name)
                ->values();

            $streamId = $streamId ? (int) $streamId : null;
            $classLabel = trim($this->class->name.' '.($streamId ? $streamNames->get($streamId) : ''));

            return [$student->getKey() => $this->studentResult($student, $streamId, $classLabel, $subjects, $comments->get($student->getKey()))];
        });

        $this->rank();
    }

    /**
     * @param  Collection<int, Mark>  $marks
     */
    private function subjectResult(Subject $subject, Collection $marks, ?string $teacher): SubjectResult
    {
        $scores = [];
        $weighted = 0.0;
        $weights = 0;

        foreach ($this->assessments as $assessment) {
            $mark = $marks->firstWhere('assessment_id', $assessment->getKey());

            if ($mark === null || $assessment->max_score <= 0) {
                continue;
            }

            $scores[$assessment->getKey()] = $mark->score;
            $weighted += $mark->score / $assessment->max_score * $assessment->weight;
            $weights += $assessment->weight;
        }

        // All weights zero: plain average of the percentages
        if ($weights === 0 && $scores !== []) {
            $percentages = collect($scores)->map(fn (float $score, int $id): float => $score / $this->assessments->firstWhere('id', $id)->max_score * 100);
            $final = $percentages->avg();
        } else {
            $final = $weights > 0 ? $weighted / $weights * 100 : null;
        }

        $final = $final === null ? null : round(min(100, max(0, $final)), 1);
        $band = $this->scale?->band($final);

        return new SubjectResult($subject, $scores, $final, $band?->grade, $band?->points, $band?->remark, $teacher);
    }

    /**
     * @param  Collection<int, SubjectResult>  $subjects
     */
    private function studentResult(Student $student, ?int $streamId, string $classLabel, Collection $subjects, ?ReportComment $comment): StudentResult
    {
        $graded = $subjects->filter(fn (SubjectResult $result): bool => $result->final !== null);
        $count = $graded->count();
        $total = round($graded->sum('final'), 1);
        $average = $count > 0 ? round($total / $count, 1) : null;
        $overall = $this->scale?->band($average);

        $aggregate = null;
        $division = null;
        $points = null;

        if ($this->scale?->summary === ResultSummary::Aggregate && $this->scale->aggregate_subjects) {
            $counted = $graded
                ->filter(fn (SubjectResult $result): bool => $result->subject->in_aggregate && $result->points !== null)
                ->sortBy('points')
                ->take($this->scale->aggregate_subjects);

            if ($counted->count() === $this->scale->aggregate_subjects) {
                $aggregate = (int) $counted->sum('points');
                $division = $this->scale->division($aggregate);
            }
        }

        if ($this->scale?->summary === ResultSummary::Points) {
            $points = round($graded->sum(fn (SubjectResult $result): float => $result->points ?? 0), 1);
        }

        return new StudentResult(
            student: $student,
            streamId: $streamId,
            classLabel: $classLabel,
            subjects: $subjects,
            subjectsCount: $count,
            total: $total,
            average: $average,
            grade: $overall?->grade,
            remark: $overall?->remark,
            aggregate: $aggregate,
            division: $division,
            points: $points,
            classTeacherComment: $comment?->class_teacher_comment,
            headTeacherComment: $comment?->head_teacher_comment,
        );
    }

    /**
     * Positions in the class and in each stream: 1, 2, 2, 4... Students with
     * no marks get no position.
     */
    private function rank(): void
    {
        $ranked = $this->students->filter(fn (StudentResult $result): bool => $result->subjectsCount > 0);

        $key = match ($this->scale?->summary) {
            ResultSummary::Aggregate => fn (StudentResult $r): array => [$r->aggregate ?? PHP_INT_MAX, -($r->average ?? 0)],
            ResultSummary::Points => fn (StudentResult $r): array => [-($r->points ?? 0), -($r->average ?? 0)],
            default => fn (StudentResult $r): array => [-($r->average ?? 0)],
        };

        $assign = function (Collection $group, string $position, string $outOf) use ($key): void {
            $sorted = $group->sort(fn (StudentResult $a, StudentResult $b): int => $key($a) <=> $key($b))->values();
            $previous = null;
            $place = 0;

            foreach ($sorted as $index => $result) {
                if ($previous === null || $key($previous) !== $key($result)) {
                    $place = $index + 1;
                }

                $result->{$position} = $place;
                $result->{$outOf} = $sorted->count();
                $previous = $result;
            }
        };

        $assign($ranked, 'position', 'outOf');

        foreach ($ranked->groupBy(fn (StudentResult $result): int => $result->streamId ?? 0) as $streamId => $group) {
            if ($streamId) {
                $assign($group, 'streamPosition', 'streamOutOf');
            }
        }
    }

    /**
     * Initials of who teaches each subject, by "subject-stream" (0 = whole class).
     *
     * @return array<string, string>
     */
    private function subjectTeachers(): array
    {
        return TeachingAssignment::query()->withoutGlobalScopes()
            ->with('user')
            ->where('school_class_id', $this->class->getKey())
            ->get()
            ->mapWithKeys(fn (TeachingAssignment $assignment): array => [
                $assignment->subject_id.'-'.($assignment->stream_id ?? 0) => static::initials($assignment->user?->name),
            ])
            ->all();
    }

    /**
     * 72.5, 80 — without trailing zeros.
     */
    public static function number(float|int|null $value): string
    {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }

    public static function initials(?string $name): string
    {
        return collect(preg_split('/\s+/', trim((string) $name)))
            ->filter()
            ->map(fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('.');
    }

    /**
     * The grading key printed on report cards.
     *
     * @return Collection<int, GradeBand>
     */
    public function gradingKey(): Collection
    {
        return $this->scale?->bands ?? collect();
    }
}
