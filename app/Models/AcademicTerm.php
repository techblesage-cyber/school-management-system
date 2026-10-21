<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AcademicTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Term 1, 2026". Exactly one term per school is marked as current; it is
 * the default wherever a term is chosen.
 */
#[Fillable(['school_id', 'year', 'term', 'starts_on', 'ends_on', 'is_current'])]
class AcademicTerm extends Model
{
    /** @use HasFactory<AcademicTermFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'term' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_current' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Marking a term as current un-marks the school's other terms
        static::saved(function (AcademicTerm $term): void {
            if ($term->is_current) {
                static::query()
                    ->withoutGlobalScopes()
                    ->where('school_id', $term->school_id)
                    ->whereKeyNot($term->getKey())
                    ->where('is_current', true)
                    ->update(['is_current' => false]);
            }
        });
    }

    public function feeItems(): HasMany
    {
        return $this->hasMany(FeeItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /**
     * The term after this one, e.g. for "Next term begins" on report cards.
     */
    public function next(): ?self
    {
        return static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $this->school_id)
            ->where(fn ($query) => $query
                ->where('year', '>', $this->year)
                ->orWhere(fn ($query) => $query->where('year', $this->year)->where('term', '>', $this->term)))
            ->orderBy('year')
            ->orderBy('term')
            ->first();
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => "Term {$this->term}, {$this->year}");
    }

    /**
     * The school's current term: the one marked current, otherwise the most
     * recent one.
     */
    public static function currentFor(School|int|null $school): ?self
    {
        $schoolId = $school instanceof School ? $school->getKey() : $school;

        if (! $schoolId) {
            return null;
        }

        return static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderByDesc('is_current')
            ->orderByDesc('year')
            ->orderByDesc('term')
            ->first();
    }

    /**
     * Term choices for forms, newest first: [id => "Term 1, 2026"].
     *
     * @return array<int, string>
     */
    public static function optionsFor(School|int|null $school): array
    {
        $schoolId = $school instanceof School ? $school->getKey() : $school;

        return static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->orderByDesc('year')
            ->orderByDesc('term')
            ->get()
            ->mapWithKeys(fn (AcademicTerm $term): array => [$term->getKey() => $term->name.($term->is_current ? ' (current)' : '')])
            ->all();
    }
}
