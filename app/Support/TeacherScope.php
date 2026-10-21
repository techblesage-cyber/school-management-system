<?php

namespace App\Support;

use App\Enums\SchoolAbility;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Models\Student;
use App\Models\TeachingAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * What a staff member may work with at a school: the subjects and classes
 * they teach, and the classes or streams they are class teacher of.
 *
 * Head teachers and administrators (who can manage exams) are unrestricted.
 * Classes are written as keys: "c:5" is all of class 5, "s:12" is stream 12.
 */
class TeacherScope
{
    /** @var Collection<int, TeachingAssignment> */
    private Collection $assignments;

    /** @var array<int, true> class ids they are class teacher of */
    private array $classTeacherOf = [];

    /** @var array<int, int> stream id => class id, for streams they are class teacher of */
    private array $streamTeacherOf = [];

    private function __construct(
        public readonly User $user,
        public readonly School $school,
        public readonly bool $unrestricted,
    ) {}

    public static function for(User $user, School $school): self
    {
        $scope = new self($user, $school, $user->canInSchool(SchoolAbility::ManageExams, $school));

        $scope->assignments = TeachingAssignment::query()->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('user_id', $user->getKey())
            ->get();

        $scope->classTeacherOf = SchoolClass::query()->withoutGlobalScopes()
            ->where('school_id', $school->getKey())
            ->where('class_teacher_id', $user->getKey())
            ->pluck('id')
            ->flip()
            ->map(fn () => true)
            ->all();

        $scope->streamTeacherOf = Stream::query()
            ->whereIn('school_class_id', $school->schoolClasses()->select('id'))
            ->where('class_teacher_id', $user->getKey())
            ->pluck('school_class_id', 'id')
            ->all();

        return $scope;
    }

    /**
     * @return array{0: int|null, 1: int|null} [class id, stream id]
     */
    public static function parseKey(?string $key): array
    {
        [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
        $id = (int) $id;

        if ($type === 'c' && $id > 0) {
            return [$id, null];
        }

        if ($type === 's' && $id > 0) {
            return [Stream::query()->whereKey($id)->value('school_class_id'), $id];
        }

        return [null, null];
    }

    /**
     * May they enter marks for this subject for students of this class/stream?
     */
    public function canMark(int $subjectId, int $classId, ?int $streamId): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        return $this->assignments->contains(fn (TeachingAssignment $a): bool => $a->subject_id === $subjectId
            && $a->school_class_id === $classId
            && ($a->stream_id === null || $a->stream_id === $streamId));
    }

    /**
     * May they mark this student in this subject?
     */
    public function canMarkStudent(int $subjectId, Student $student): bool
    {
        return $student->school_id === $this->school->getKey()
            && $student->school_class_id !== null
            && $this->canMark($subjectId, $student->school_class_id, $student->stream_id);
    }

    /**
     * Subjects they can enter marks for: [id => name].
     *
     * @return array<int, string>
     */
    public function markableSubjects(): array
    {
        $query = $this->school->subjects()->orderBy('name');

        if (! $this->unrestricted) {
            $query->whereIn('id', $this->assignments->pluck('subject_id')->unique());
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * Class/stream choices for marking a subject, grouped by class.
     *
     * @return array<string, array<string, string>>
     */
    public function markableClassOptions(?int $subjectId): array
    {
        $options = TeachingAssignment::classOptions($this->school);

        if ($this->unrestricted) {
            return $options;
        }

        $allowed = $this->assignments
            ->filter(fn (TeachingAssignment $a): bool => $a->subject_id === $subjectId)
            ->map(fn (TeachingAssignment $a): string => $a->stream_id ? "s:{$a->stream_id}" : "c:{$a->school_class_id}")
            ->all();

        return $this->filterOptions($options, $allowed);
    }

    /**
     * May they see (and print) results and attendance for this class/stream?
     * Teachers see classes they teach any subject in, or are class teacher of.
     */
    public function canSeeClass(int $classId, ?int $streamId = null): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        if (isset($this->classTeacherOf[$classId]) || ($streamId && ($this->streamTeacherOf[$streamId] ?? null) === $classId)) {
            return true;
        }

        // A whole class needs a whole-class assignment; a stream needs either
        return $this->assignments->contains(fn (TeachingAssignment $a): bool => $a->school_class_id === $classId
            && ($a->stream_id === null || $a->stream_id === $streamId));
    }

    /**
     * Class/stream choices for results and attendance.
     *
     * @return array<string, array<string, string>>
     */
    public function visibleClassOptions(): array
    {
        $options = TeachingAssignment::classOptions($this->school);

        if ($this->unrestricted) {
            return $options;
        }

        $allowed = [
            ...array_map(fn (int $id): string => "c:{$id}", array_keys($this->classTeacherOf)),
            ...array_map(fn (int $id): string => "s:{$id}", array_keys($this->streamTeacherOf)),
            ...$this->assignments->map(fn (TeachingAssignment $a): string => $a->stream_id ? "s:{$a->stream_id}" : "c:{$a->school_class_id}")->all(),
        ];

        return $this->filterOptions($options, $allowed);
    }

    /**
     * Class teachers write the class teacher's comment for their students.
     */
    public function isClassTeacherOf(int $classId, ?int $streamId): bool
    {
        return isset($this->classTeacherOf[$classId]) || ($streamId !== null && ($this->streamTeacherOf[$streamId] ?? null) === $classId);
    }

    public function canWriteClassTeacherComment(int $classId, ?int $streamId): bool
    {
        return $this->unrestricted || $this->isClassTeacherOf($classId, $streamId);
    }

    /**
     * Keeps allowed keys. A whole class ("c:5") also allows its streams.
     *
     * @param  array<string, array<string, string>>  $options
     * @param  array<int, string>  $allowed
     * @return array<string, array<string, string>>
     */
    private function filterOptions(array $options, array $allowed): array
    {
        $result = [];

        foreach ($options as $group => $choices) {
            $wholeClassAllowed = collect(array_keys($choices))->contains(fn (string $key): bool => str_starts_with($key, 'c:') && in_array($key, $allowed, true));
            $kept = array_filter($choices, fn (string $key): bool => $wholeClassAllowed || in_array($key, $allowed, true), ARRAY_FILTER_USE_KEY);

            if ($kept !== []) {
                $result[$group] = $kept;
            }
        }

        return $result;
    }
}
