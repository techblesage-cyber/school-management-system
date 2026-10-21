<?php

namespace App\Support\Results;

use App\Models\Subject;

/**
 * One subject on a report card.
 */
class SubjectResult
{
    /**
     * @param  array<int, float>  $scores  Score per assessment id
     */
    public function __construct(
        public readonly Subject $subject,
        public readonly array $scores,
        public readonly ?float $final,
        public readonly ?string $grade,
        public readonly ?float $points,
        public readonly ?string $remark,
        public readonly ?string $teacher,
    ) {}
}
