<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\HasYearlySequence;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A student's fees bill for one term. Its total is kept up to date from its
 * lines automatically.
 */
#[Fillable(['school_id', 'student_id', 'academic_term_id', 'number', 'issued_on', 'notes'])]
class Invoice extends Model
{
    use BelongsToSchool, HasYearlySequence;

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            $invoice->issued_on ??= now();

            if (blank($invoice->number) && filled($invoice->school_id)) {
                $invoice->number = static::nextSequenceNumber($invoice->school_id, 'number', 'INV-'.$invoice->issued_on->year.'-');
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function recalculateTotal(): void
    {
        $this->forceFill(['total' => (int) $this->items()->sum('amount')])->saveQuietly();
    }

    /**
     * Adds the fee structure lines that apply to this student and term,
     * skipping any already on the invoice.
     */
    public function addFeeLines(): int
    {
        $student = $this->student;
        $existing = $this->items()->whereNotNull('fee_item_id')->pluck('fee_item_id')->all();
        $added = 0;

        $items = FeeItem::query()
            ->withoutGlobalScopes()
            ->where('school_id', $this->school_id)
            ->where('academic_term_id', $this->academic_term_id)
            ->appliesTo($student)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            if (in_array($item->getKey(), $existing, true)) {
                continue;
            }

            $this->items()->create([
                'fee_item_id' => $item->getKey(),
                'description' => $item->name,
                'amount' => $item->amount,
            ]);
            $added++;
        }

        $this->recalculateTotal();

        return $added;
    }

    /**
     * Bills every active student of a school (optionally only some classes)
     * for a term. Students who already have a bill for that term are skipped,
     * so running it again only bills new students.
     *
     * @param  array<int, int>  $classIds
     * @return int Number of invoices created
     */
    public static function billTerm(AcademicTerm $term, array $classIds = []): int
    {
        $students = Student::query()
            ->withoutGlobalScopes()
            ->where('school_id', $term->school_id)
            ->where('status', StudentStatus::Active)
            ->whereNotNull('school_class_id')
            ->when($classIds !== [], fn ($query) => $query->whereIn('school_class_id', $classIds))
            ->whereDoesntHave('invoices', fn ($query) => $query->withoutGlobalScopes()->where('academic_term_id', $term->getKey()))
            ->get();

        foreach ($students as $student) {
            DB::transaction(function () use ($student, $term): void {
                $invoice = static::query()->withoutGlobalScopes()->create([
                    'school_id' => $term->school_id,
                    'student_id' => $student->getKey(),
                    'academic_term_id' => $term->getKey(),
                ]);

                $invoice->setRelation('student', $student);
                $invoice->addFeeLines();
            });
        }

        return $students->count();
    }
}
