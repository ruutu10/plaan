<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Show;
use Carbon\CarbonInterval;
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
            'date' => Performance::momentFrom(
                fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
                fake()->randomElement(['18:00', '19:00', '20:00', '21:30']),
            ),
            'duration' => fake()->numberBetween(3, 90),
            'is_draft' => false,
        ];
    }

    /**
     * Put the performance at a given moment on the venue's clock, which is how
     * a test means a date and time — "the first of September at seven", not the
     * UTC the row happens to hold.
     */
    public function startingAt(string $date, string $startTime = '19:00'): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => Performance::momentFrom($date, $startTime),
        ]);
    }

    /**
     * Put the performance a given interval from now, for the reminder tests —
     * "in five days" being the thing those are actually about.
     */
    public function startingIn(CarbonInterval $ahead): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => now()->add($ahead),
        ]);
    }

    /**
     * Indicate that the performance is still waiting to be reviewed, as the ones
     * the Planka import registers do.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_draft' => true,
        ]);
    }

    /**
     * Indicate that the performance already happened.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => Performance::momentFrom(
                fake()->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
            ),
        ]);
    }
}
