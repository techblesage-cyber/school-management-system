<?php

namespace App\Models\Concerns;

/**
 * Generates numbers like "2026/0001" that restart every year and are
 * counted separately for each school.
 */
trait HasYearlySequence
{
    /**
     * Next free number for the school, e.g. "2026/0007" or "APP-2026-0007".
     */
    public static function nextSequenceNumber(int $schoolId, string $column, string $prefix, int $digits = 4): string
    {
        $highest = static::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->where($column, 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck($column)
            ->map(fn (string $value): int => (int) substr($value, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highest + 1), $digits, '0', STR_PAD_LEFT);
    }
}
