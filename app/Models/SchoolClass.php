<?php

namespace App\Models;

use App\Enums\ClassLevel;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A class such as "P.5" or "S.2". Named SchoolClass because "Class" is a
 * reserved word in PHP.
 */
#[Fillable(['school_id', 'name', 'level', 'sort_order'])]
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'level' => ClassLevel::class,
            'sort_order' => 'integer',
        ];
    }

    public function streams(): HasMany
    {
        return $this->hasMany(Stream::class)->orderBy('name');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
