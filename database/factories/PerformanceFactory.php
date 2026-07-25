<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Show;
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
            'show_id' => Show::factory(),
            'date' => fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
            'duration' => fake()->numberBetween(3, 90),
        ];
    }

    /**
     * Indicate that the performance already happened.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
        ]);
    }
}
