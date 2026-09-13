<?php

namespace Database\Factories;

use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicalPlanComment>
 */
class TechnicalPlanCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'technical_plan_id' => TechnicalPlan::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->sentence(),
            'from_technical_team' => false,
        ];
    }

    /**
     * Indicate that the crew running the formats wrote this one.
     */
    public function fromTechnicalTeam(): static
    {
        return $this->state(fn (array $attributes) => [
            'from_technical_team' => true,
        ]);
    }
}
