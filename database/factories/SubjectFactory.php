<?php

namespace Database\Factories;

use App\Models\School;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => fake()->unique()->randomElement(['Mathematics', 'English', 'Biology', 'Chemistry', 'Physics', 'Geography', 'History', 'Kiswahili', 'Agriculture', 'Entrepreneurship', 'ICT', 'Fine Art']),
        ];
    }
}
