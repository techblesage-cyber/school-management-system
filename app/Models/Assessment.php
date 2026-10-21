<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An exam or test in a term, e.g. "End of term" out of 100.
 */
#[Fillable(['school_id', 'academic_term_id', 'name', 'max_score', 'weight', 'sort_order'])]
class Assessment extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * Classes that sit this assessment. None means every class.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function scopeForClass(Builder $query, int $classId): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereDoesntHave('classes')
            ->orWhereHas('classes', fn (Builder $query) => $query->whereKey($classId)));
    }

    public function appliesToClass(int $classId): bool
    {
        $classIds = $this->classes()->pluck('school_classes.id');

        return $classIds->isEmpty() || $classIds->contains($classId);
    }
}
