<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Performance>
 */
class PerformanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'show_name' => fake()->words(3, true),
            'show_date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'duration' => fake()->numberBetween(3, 90),
            'description' => fake()->paragraph(),
        ];
    }

    /**
     * Indicate that the performance already happened.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_date' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
        ]);
    }
}
