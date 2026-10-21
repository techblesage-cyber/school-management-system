<?php

namespace App\Support\Results;

use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * A student's result for a term: subjects, overall result and positions.
 */
class StudentResult
{
    public ?int $position = null;

    public ?int $outOf = null;

    public ?int $streamPosition = null;

    public ?int $streamOutOf = null;

    /**
     * @param  Collection<int, SubjectResult>  $subjects
     */
    public function __construct(
        public readonly Student $student,
        public readonly ?int $streamId,
        public readonly string $classLabel,
        public readonly Collection $subjects,
        public readonly int $subjectsCount,
        public readonly float $total,
        public readonly ?float $average,
        public readonly ?string $grade,
        public readonly ?string $remark,
        public readonly ?int $aggregate,
        public readonly ?string $division,
        public readonly ?float $points,
        public readonly ?string $classTeacherComment,
        public readonly ?string $headTeacherComment,
    ) {}

    public function hasMarks(): bool
    {
        return $this->subjectsCount > 0;
    }

    /**
     * A suggested class teacher comment from the average mark.
     */
    public function suggestedComment(): string
    {
        return match (true) {
            $this->average === null => 'No marks this term.',
            $this->average >= 80 => 'Excellent work. Keep it up.',
            $this->average >= 65 => 'Very good work. Aim even higher.',
            $this->average >= 50 => 'Good effort. Work harder to improve further.',
            $this->average >= 40 => 'Fair. More effort is needed.',
            default => 'Weak results. Needs serious attention and support.',
        };
    }
}
