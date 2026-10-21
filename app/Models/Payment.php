<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasYearlySequence;
use App\Services\Sms\Sms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Money received for a student. Each payment gets a receipt number. A
 * mistaken payment is voided (with a reason), never edited or deleted.
 */
#[Fillable(['school_id', 'student_id', 'academic_term_id', 'amount', 'paid_on', 'method', 'reference', 'received_by', 'notes'])]
class Payment extends Model
{
    use BelongsToSchool, HasYearlySequence;

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->paid_on ??= now();
            $payment->received_by ??= auth()->id();
            $payment->academic_term_id ??= AcademicTerm::currentFor($payment->school_id)?->getKey();

            if (blank($payment->receipt_number) && filled($payment->school_id)) {
                $payment->receipt_number = static::nextSequenceNumber($payment->school_id, 'receipt_number', 'RCT-'.$payment->paid_on->year.'-');
            }
        });

        // Texts the parent a receipt, if the school uses SMS receipts
        static::created(fn (Payment $payment) => Sms::paymentReceipt($payment));
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function scopeValid(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * The student's balance straight after this payment, on the fee
     * statement's term-by-term order. Stays the same when the receipt is
     * reprinted later. Null for a voided payment.
     */
    public function balanceAfter(): ?int
    {
        $line = Student::query()->withoutGlobalScopes()->find($this->student_id)?->feeLedger()
            ->first(fn (array $line): bool => $line['type'] === 'payment' && $line['model']->is($this));

        return $line['balance'] ?? null;
    }

    public function void(string $reason, ?User $by = null): void
    {
        if ($this->isVoided()) {
            throw new LogicException("Receipt {$this->receipt_number} is already voided.");
        }

        $this->forceFill([
            'voided_at' => now(),
            'voided_by' => ($by ?? auth()->user())?->getKey(),
            'void_reason' => $reason,
        ])->save();
    }
}
