<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "D1 from 80%, 1 point, Excellent".
 */
#[Fillable(['grading_scale_id', 'grade', 'min_score', 'points', 'remark'])]
class GradeBand extends Model
{
    protected function casts(): array
    {
        return [
            'min_score' => 'float',
            'points' => 'float',
        ];
    }

    public function gradingScale(): BelongsTo
    {
        return $this->belongsTo(GradingScale::class);
    }
}
