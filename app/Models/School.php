<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'slug', 'email', 'phone', 'address', 'motto', 'logo', 'is_active', 'show_positions', 'report_shows_fees', 'sms_enabled', 'sms_payment_receipts'])]
class School extends Model implements HasAvatar, HasName
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_positions' => 'boolean',
            'report_shows_fees' => 'boolean',
            'sms_enabled' => 'boolean',
            'sms_payment_receipts' => 'boolean',
        ];
    }

    /**
     * Staff members of this school, with their role in the pivot.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function academicTerms(): HasMany
    {
        return $this->hasMany(AcademicTerm::class);
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(FeeItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function gradingScales(): HasMany
    {
        return $this->hasMany(GradingScale::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function circulars(): HasMany
    {
        return $this->hasMany(Circular::class);
    }

    public function admissionApplications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo ? Storage::disk('public')->url($this->logo) : null;
    }
}
