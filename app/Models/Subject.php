<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['school_id', 'name', 'code', 'in_aggregate'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'in_aggregate' => 'boolean',
        ];
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }
}
