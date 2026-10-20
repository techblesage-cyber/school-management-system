<?php

namespace Database\Factories;

use App\Enums\ClassLevel;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolClass>
 */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'S.'.fake()->unique()->numberBetween(1, 999),
            'level' => ClassLevel::OLevel,
            'sort_order' => 0,
        ];
    }
}
