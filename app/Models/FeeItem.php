<?php

namespace App\Models;

use App\Enums\Residence;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One line of the fee structure, e.g. "Tuition, UGX 850,000, S.1–S.4" or
 * "Boarding, UGX 600,000, boarders only".
 */
#[Fillable(['school_id', 'academic_term_id', 'name', 'amount', 'residence'])]
class FeeItem extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'residence' => Residence::class,
        ];
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    /**
     * Classes this item applies to. None means every class.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class);
    }

    /**
     * Items a given student pays in a term, based on their class and
     * whether they are a day scholar or boarder.
     */
    public function scopeAppliesTo(Builder $query, Student $student): Builder
    {
        return $query
            ->where(fn (Builder $query) => $query
                ->whereDoesntHave('classes')
                ->orWhereHas('classes', fn (Builder $query) => $query->whereKey($student->school_class_id)))
            ->where(fn (Builder $query) => $query
                ->whereNull('residence')
                ->orWhere('residence', $student->residence));
    }
}
