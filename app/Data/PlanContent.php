<?php

namespace App\Data;

use App\Models\Performance;

/**
 * The plan's own content, taken out of validated wizard input: the four JSON
 * blocks a plan is made of, normalised the way they are stored.
 *
 * Wanted in two places — saving a plan, and hydrating an unsaved one for the AI
 * reviewer — which is why it is not simply inlined at either.
 */
class PlanContent
{
    /**
     * @param  array<string, mixed>  $data  Validated wizard input.
     * @param  Performance|null  $performance  The night the plan is for, whose venue settles whether smoke is possible at all.
     * @return array<string, mixed>
     */
    public static function fromValidated(array $data, ?Performance $performance = null): array
    {
        $equipment = array_merge($data['equipment'], [
            'items' => array_values($data['equipment']['items'] ?? []),
        ]);

        // The wizard already answers "no" for a hall that cannot take smoke,
        // but that is the browser's word; the stored plan keeps the house rule
        // whatever was posted.
        if ($performance?->allowsSmoke() === false) {
            $equipment['smoke'] = 'no';
        }

        return [
            'sound' => $data['sound'],
            // The wizard may have removed rows, leaving gaps in the keys; the
            // stored JSON is a list, so it is re-indexed on the way in.
            'scenes' => array_values($data['scenes']),
            'equipment' => $equipment,
            'extra' => ['notes' => $data['extra']['notes'] ?? ''],
        ];
    }
}
