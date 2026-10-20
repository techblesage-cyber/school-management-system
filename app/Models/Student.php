<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Residence;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasYearlySequence;
use Database\Factories\StudentFactory;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'school_id', 'admission_number', 'lin', 'first_name', 'last_name', 'other_names', 'gender',
    'date_of_birth', 'nationality', 'photo', 'school_class_id', 'stream_id', 'residence',
    'admission_date', 'status', 'previous_school', 'entry_index_number', 'home_address', 'notes',
])]
class Student extends Model implements HasAvatar
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, HasYearlySequence;

    protected $attributes = [
        'residence' => 'day',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'residence' => Residence::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'admission_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Runs after BelongsToSchool has filled in school_id
        static::creating(function (Student $student): void {
            $student->admission_date ??= now();

            if (blank($student->admission_number) && filled($student->school_id)) {
                $student->admission_number = static::nextAdmissionNumber($student->school_id, $student->admission_date->year);
            }
        });
    }

    /**
     * Admission numbers look like "2026/0001" and restart every year.
     */
    public static function nextAdmissionNumber(int $schoolId, ?int $year = null): string
    {
        return static::nextSequenceNumber($schoolId, 'admission_number', ($year ?? now()->year).'/');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)
            ->withPivot(['relationship', 'is_primary'])
            ->withTimestamps();
    }

    public function application(): HasOne
    {
        return $this->hasOne(AdmissionApplication::class);
    }

    /**
     * "Surname First Other", the order used on most Ugandan school records.
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->last_name} {$this->first_name} {$this->other_names}"));
    }

    /**
     * "S.1 East", or just "S.1" when the student has no stream.
     */
    protected function classAndStream(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->schoolClass
            ? trim($this->schoolClass->name.' '.$this->stream?->name)
            : null);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url($this->photo) : null;
    }
}
