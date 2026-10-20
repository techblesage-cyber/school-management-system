<?php

namespace App\Models\Concerns;

use App\Models\School;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records that belong to one school (students, classes, guardians...).
 *
 * Inside the school portal, Filament already limits every query to the
 * current school and fills in school_id. This trait adds the relationship
 * Filament relies on, and fills school_id when a record is created in code.
 */
trait BelongsToSchool
{
    public static function bootBelongsToSchool(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->school_id) && ($school = Filament::getTenant()) instanceof School) {
                $model->school_id = $school->getKey();
            }
        });
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
