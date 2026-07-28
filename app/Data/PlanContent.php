<?php

namespace App\Data;

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
     * @return array<string, mixed>
     */
    public static function fromValidated(array $data): array
    {
        return [
            'sound' => $data['sound'],
            // The wizard may have removed rows, leaving gaps in the keys; the
            // stored JSON is a list, so it is re-indexed on the way in.
            'scenes' => array_values($data['scenes']),
            'equipment' => array_merge($data['equipment'], [
                'items' => array_values($data['equipment']['items'] ?? []),
            ]),
            'extra' => ['notes' => $data['extra']['notes'] ?? ''],
        ];
    }
}
