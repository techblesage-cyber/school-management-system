<?php

namespace Database\Factories;

use App\Models\AcademicTerm;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicTerm>
 */
class AcademicTermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'year' => 2026,
            'term' => 3,
            'is_current' => true,
        ];
    }
}
