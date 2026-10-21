<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Residence;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasYearlySequence;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable([
    'school_id', 'reference', 'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth',
    'school_class_id', 'previous_school', 'entry_index_number', 'guardian_name', 'guardian_phone',
    'guardian_email', 'guardian_relationship', 'applied_on', 'status', 'notes',
])]
class AdmissionApplication extends Model
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use BelongsToSchool, HasFactory, HasYearlySequence;

    protected $attributes = [
        'status' => 'pending',
        'guardian_relationship' => 'guardian',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => ApplicationStatus::class,
            'guardian_relationship' => GuardianRelationship::class,
            'date_of_birth' => 'date',
            'applied_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AdmissionApplication $application): void {
            $application->applied_on ??= now();

            if (blank($application->reference) && filled($application->school_id)) {
                $application->reference = static::nextSequenceNumber(
                    $application->school_id,
                    'reference',
                    'APP-'.$application->applied_on->year.'-',
                );
            }
        });
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->last_name} {$this->first_name} {$this->other_names}"));
    }

    public function canBeEnrolled(): bool
    {
        return in_array($this->status, [ApplicationStatus::Pending, ApplicationStatus::Accepted], true);
    }

    /**
     * Turns the application into a student record, linking the parent
     * (reusing an existing parent record with the same phone number).
     *
     * @param  array{school_class_id: int, stream_id?: int|null, residence?: string|Residence, admission_date?: mixed, photo?: string|null}  $enrolment
     */
    public function enrol(array $enrolment): Student
    {
        if (! $this->canBeEnrolled()) {
            throw new LogicException("Application {$this->reference} has already been {$this->status->getLabel()}.");
        }

        return DB::transaction(function () use ($enrolment): Student {
            $student = Student::create([
                'school_id' => $this->school_id,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'other_names' => $this->other_names,
                'gender' => $this->gender,
                'date_of_birth' => $this->date_of_birth,
                'previous_school' => $this->previous_school,
                'entry_index_number' => $this->entry_index_number,
                'school_class_id' => $enrolment['school_class_id'],
                'stream_id' => $enrolment['stream_id'] ?? null,
                'residence' => $enrolment['residence'] ?? Residence::Day,
                'admission_date' => $enrolment['admission_date'] ?? now(),
                'photo' => $enrolment['photo'] ?? null,
            ]);

            $guardian = Guardian::findByPhone($this->school_id, $this->guardian_phone)
                ?? Guardian::create([
                    'school_id' => $this->school_id,
                    'name' => $this->guardian_name,
                    'phone' => $this->guardian_phone,
                    'email' => $this->guardian_email,
                ]);

            $student->guardians()->attach($guardian, [
                'relationship' => $this->guardian_relationship->value,
                'is_primary' => true,
            ]);

            $this->forceFill([
                'status' => ApplicationStatus::Enrolled,
                'student_id' => $student->getKey(),
            ])->save();

            return $student;
        });
    }
}
