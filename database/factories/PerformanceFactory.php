<?php

namespace Database\Factories;

use App\Enums\CreatedBy;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
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
            'format_id' => Format::factory(),
            // The format's own group plays it, under the format's own name — the
            // ordinary case. A shared evening says otherwise; see performedBy().
            'team_id' => null,
            'title' => null,
            'date' => Performance::momentFrom(
                fake()->dateTimeBetween('now', '+2 months')->format('Y-m-d'),
                fake()->randomElement(['18:00', '19:00', '20:00', '21:30']),
            ),
            'duration' => fake()->numberBetween(3, 90),
            'is_draft' => false,
            'created_by' => CreatedBy::Manual,
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
     * Put a group of its own on the performance, as an act on an evening the
     * format's owner shares with others — optionally under the name the board
     * gives the act.
     */
    public function performedBy(Team $team, ?string $title = null): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $team->id,
            'title' => $title,
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
     * Indicate that the performance was registered off a Planka card rather than
     * entered by hand — as the import leaves them, waiting to be reviewed.
     */
    public function plankaImported(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => CreatedBy::PlankaImport,
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
