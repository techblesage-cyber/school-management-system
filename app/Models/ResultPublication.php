<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a class's report cards for a term as released to parents.
 */
#[Fillable(['school_id', 'academic_term_id', 'school_class_id', 'published_by'])]
class ResultPublication extends Model
{
    use BelongsToSchool;

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public static function isPublished(int $termId, ?int $classId): bool
    {
        return $classId !== null && static::query()
            ->withoutGlobalScopes()
            ->where('academic_term_id', $termId)
            ->where('school_class_id', $classId)
            ->exists();
    }
}
