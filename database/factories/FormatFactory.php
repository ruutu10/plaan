<?php

namespace Database\Factories;

use App\Enums\CreatedBy;
use App\Models\Format;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Format>
 */
class FormatFactory extends Factory
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
            'technical_plan_mandatory' => true,
            'created_by' => CreatedBy::Manual,
        ];
    }

    /**
     * Indicate that the format is one of the nights that run themselves, which
     * nobody is chased about a missing technical plan for.
     */
    public function withoutMandatoryTechnicalPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'technical_plan_mandatory' => false,
        ]);
    }

    /**
     * Indicate that the format was registered by the Planka import rather than
     * entered by hand.
     */
    public function plankaImported(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => CreatedBy::PlankaImport,
        ]);
    }
}
