<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\Residence;
use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasPhoto;
use App\Models\Concerns\HasYearlySequence;
use Database\Factories\StudentFactory;
use Filament\Models\Contracts\HasAvatar;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

#[Fillable([
    'school_id', 'admission_number', 'lin', 'first_name', 'last_name', 'other_names', 'gender',
    'date_of_birth', 'nationality', 'photo', 'school_class_id', 'stream_id', 'residence',
    'admission_date', 'status', 'previous_school', 'entry_index_number', 'home_address', 'notes',
])]
class Student extends Model implements HasAvatar
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToSchool, HasFactory, HasPhoto, HasYearlySequence;

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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * SQL for "billed minus paid" (voided payments don't count). A positive
     * balance is owed by the family; a negative one is credit.
     */
    public static function feeBalanceSql(): string
    {
        return '(select coalesce(sum(invoices.total), 0) from invoices where invoices.student_id = students.id)'
            .' - (select coalesce(sum(payments.amount), 0) from payments where payments.student_id = students.id and payments.voided_at is null)';
    }

    /**
     * Adds fees_billed, fees_paid and fee_balance to each student.
     */
    public function scopeWithFeeBalance(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('students.*');
        }

        return $query
            ->selectRaw('(select coalesce(sum(invoices.total), 0) from invoices where invoices.student_id = students.id) as fees_billed')
            ->selectRaw('(select coalesce(sum(payments.amount), 0) from payments where payments.student_id = students.id and payments.voided_at is null) as fees_paid')
            ->selectRaw(static::feeBalanceSql().' as fee_balance');
    }

    /**
     * What the family owes right now (negative = credit).
     */
    public function feeBalance(): int
    {
        return (int) $this->invoices()->withoutGlobalScopes()->sum('total')
            - (int) $this->payments()->withoutGlobalScopes()->valid()->sum('amount');
    }

    /**
     * The student's fees history, term by term: each term's invoice first,
     * then the payments made for that term, with a running balance. Used by
     * the fee statement and by receipts, so both always agree.
     *
     * @return Collection<int, array{type: string, model: Invoice|Payment, date: CarbonInterface, details: string, debit: int, credit: int, balance: int}>
     */
    public function feeLedger(): Collection
    {
        $termKey = fn (?AcademicTerm $term): array => $term ? [$term->year, $term->term] : [PHP_INT_MAX, PHP_INT_MAX];

        $invoices = $this->invoices()->withoutGlobalScopes()->with('academicTerm')->get()
            ->map(fn (Invoice $invoice): array => [
                'type' => 'invoice',
                'model' => $invoice,
                'date' => $invoice->issued_on,
                'details' => "Invoice {$invoice->number} · {$invoice->academicTerm->name}",
                'debit' => $invoice->total,
                'credit' => 0,
                'sort' => [...$termKey($invoice->academicTerm), 0, $invoice->issued_on->timestamp, $invoice->getKey()],
            ]);

        $payments = $this->payments()->withoutGlobalScopes()->valid()->with('academicTerm')->get()
            ->map(fn (Payment $payment): array => [
                'type' => 'payment',
                'model' => $payment,
                'date' => $payment->paid_on,
                'details' => "Receipt {$payment->receipt_number} · {$payment->method->getLabel()}".($payment->reference ? " ({$payment->reference})" : ''),
                'debit' => 0,
                'credit' => $payment->amount,
                'sort' => [...$termKey($payment->academicTerm), 1, $payment->paid_on->timestamp, $payment->getKey()],
            ]);

        $running = 0;

        return $invoices->concat($payments)
            ->sort(fn (array $a, array $b): int => $a['sort'] <=> $b['sort'])
            ->values()
            ->map(function (array $line) use (&$running): array {
                $running += $line['debit'] - $line['credit'];
                unset($line['sort']);

                return [...$line, 'balance' => $running];
            });
    }

    public function hasFeeRecords(): bool
    {
        return $this->invoices()->withoutGlobalScopes()->exists()
            || $this->payments()->withoutGlobalScopes()->exists();
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function reportComments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
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
        return $this->photoUrl();
    }
}
