<?php

namespace Database\Factories;

use App\Models\ClaudeReasoningLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClaudeReasoningLog>
 */
class ClaudeReasoningLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'card_id' => fake()->uuid(),
            'card_name' => fake()->words(3, true),
            'notes' => [
                'Kuupäev real "Toimumise kuupäev: 9.10.2025".',
                'Tom on heli ja valgus, seega meeskond, mitte esineja.',
            ],
            'raw_response' => [
                'formats' => [],
                'reasoningNotes' => [
                    'Kuupäev real "Toimumise kuupäev: 9.10.2025".',
                    'Tom on heli ja valgus, seega meeskond, mitte esineja.',
                ],
            ],
        ];
    }
}
