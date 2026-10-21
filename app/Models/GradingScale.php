<?php

namespace App\Models;

use App\Enums\ResultSummary;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Turns a mark (as a percentage) into a grade, points and remark.
 */
#[Fillable(['school_id', 'name', 'summary', 'aggregate_subjects', 'divisions'])]
class GradingScale extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'summary' => ResultSummary::class,
            'aggregate_subjects' => 'integer',
            'divisions' => 'array',
        ];
    }

    /**
     * Highest grade first.
     */
    public function bands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderByDesc('min_score');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function band(?float $percentage): ?GradeBand
    {
        if ($percentage === null) {
            return null;
        }

        return $this->bands->first(fn (GradeBand $band): bool => round($percentage, 2) >= (float) $band->min_score);
    }

    /**
     * "Division I" for an aggregate of 8, using this scale's divisions.
     */
    public function division(?int $aggregate): ?string
    {
        if ($aggregate === null || empty($this->divisions)) {
            return null;
        }

        foreach (collect($this->divisions)->sortBy('max') as $division) {
            if ($aggregate <= (int) $division['max']) {
                return $division['label'];
            }
        }

        return 'Ungraded (U)';
    }

    /**
     * The usual Ugandan scales. Schools can change any band afterwards.
     *
     * @return array<string, array{summary: ResultSummary, aggregate_subjects?: int, divisions?: array<int, array{max: int, label: string}>, bands: array<int, array{0: string, 1: float, 2: float|null, 3: string}>}>
     */
    public static function standardScales(): array
    {
        return [
            'Primary (D1–F9)' => [
                'summary' => ResultSummary::Aggregate,
                'aggregate_subjects' => 4,
                'divisions' => [
                    ['max' => 12, 'label' => 'Division I'],
                    ['max' => 23, 'label' => 'Division II'],
                    ['max' => 29, 'label' => 'Division III'],
                    ['max' => 33, 'label' => 'Division IV'],
                ],
                'bands' => [
                    ['D1', 80, 1, 'Excellent'], ['D2', 70, 2, 'Very good'], ['C3', 65, 3, 'Good'],
                    ['C4', 60, 4, 'Good'], ['C5', 55, 5, 'Fair'], ['C6', 50, 6, 'Fair'],
                    ['P7', 45, 7, 'Pass'], ['P8', 40, 8, 'Pass'], ['F9', 0, 9, 'Fail'],
                ],
            ],
            'O-level (A–E)' => [
                'summary' => ResultSummary::Average,
                'bands' => [
                    ['A', 83, null, 'Exceptional'], ['B', 67, null, 'Outstanding'], ['C', 50, null, 'Satisfactory'],
                    ['D', 30, null, 'Basic'], ['E', 0, null, 'Elementary'],
                ],
            ],
            'A-level (A–F)' => [
                'summary' => ResultSummary::Points,
                'bands' => [
                    ['A', 80, 6, 'Excellent'], ['B', 70, 5, 'Very good'], ['C', 60, 4, 'Good'],
                    ['D', 50, 3, 'Fair'], ['E', 45, 2, 'Pass'], ['O', 35, 1, 'Subsidiary pass'], ['F', 0, 0, 'Fail'],
                ],
            ],
        ];
    }

    /**
     * Adds a standard scale to a school, unless one with that name exists.
     */
    public static function addStandard(School $school, string $name): ?self
    {
        $definition = static::standardScales()[$name] ?? null;

        if (! $definition || $school->gradingScales()->where('name', $name)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($school, $name, $definition): self {
            $scale = $school->gradingScales()->create([
                'name' => $name,
                'summary' => $definition['summary'],
                'aggregate_subjects' => $definition['aggregate_subjects'] ?? null,
                'divisions' => $definition['divisions'] ?? null,
            ]);

            foreach ($definition['bands'] as [$grade, $min, $points, $remark]) {
                $scale->bands()->create(['grade' => $grade, 'min_score' => $min, 'points' => $points, 'remark' => $remark]);
            }

            return $scale;
        });
    }
}
