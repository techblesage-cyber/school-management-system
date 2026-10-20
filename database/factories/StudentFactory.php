<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());

        return [
            'school_id' => School::factory(),
            'first_name' => fake()->firstName($gender->value),
            'last_name' => fake()->lastName(),
            'gender' => $gender,
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'admission_date' => now(),
        ];
    }
}
