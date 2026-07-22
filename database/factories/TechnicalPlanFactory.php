<?php

namespace Database\Factories;

use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicalPlan>
 */
class TechnicalPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => TechnicalPlanStatus::Draft,
            'user_id' => User::factory(),
            'performance_id' => Performance::factory(),
            'sound' => [
                'micsMode' => 'no',
                'micsDetail' => '',
                'musicianMode' => 'no',
                'musicianDetail' => '',
            ],
            'scenes' => [
                ['id' => 'stseen-1', 'name' => 'Lavale tulek', 'light' => 'Soe üldvalgus', 'soundUrl' => '', 'sound' => '', 'notes' => ''],
            ],
            'equipment' => [
                'items' => [],
                'smoke' => 'yes',
                'suggestions' => 'yes',
                'suggestNote' => '',
            ],
            'extra' => [
                'notes' => '',
                'files' => [],
            ],
        ];
    }

    /**
     * Indicate that the plan has been submitted to the technical team.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TechnicalPlanStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
