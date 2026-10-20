<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->lastName().' '.fake()->randomElement(['Primary School', 'High School', 'College']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('07########'),
            'address' => fake()->address(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
