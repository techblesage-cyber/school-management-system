<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's score in one subject for one assessment.
 */
#[Fillable(['school_id', 'assessment_id', 'student_id', 'subject_id', 'school_class_id', 'stream_id', 'score', 'entered_by'])]
class Mark extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
