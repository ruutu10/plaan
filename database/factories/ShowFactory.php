<?php

namespace Database\Factories;

use App\Enums\CreatedBy;
use App\Models\Show;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Show>
 */
class ShowFactory extends Factory
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
            'name' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'created_by' => CreatedBy::Manual,
        ];
    }

    /**
     * Indicate that the show was registered by the Planka import rather than
     * entered by hand.
     */
    public function plankaImported(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => CreatedBy::PlankaImport,
        ]);
    }
}
