<?php

namespace App\Actions;

use App\Data\PlanContent;
use App\Enums\TechnicalPlanStatus;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Write a plan — a first draft, a correction to one, or a submission — and put
 * its files in place.
 *
 * The caller is expected to have settled whether this user may write to the
 * plan at all; by the time it gets here that question is closed.
 */
class SaveTechnicalPlan
{
    /**
     * @param  TechnicalPlan  $plan  An existing plan, or a new one carrying the token to save under.
     * @param  array<string, mixed>  $data  Validated wizard input.
     */
    public function handle(TechnicalPlan $plan, array $data, User $user, bool $submitting): TechnicalPlan
    {
        $wasNew = ! $plan->exists;

        $attributes = PlanContent::fromValidated($data) + [
            'performance_id' => $data['meta']['performanceId'],
        ];

        // A plan's owner is settled when it is created; a later save never
        // reassigns it, or holding the share link would be enough to take it.
        if ($wasNew) {
            $attributes['user_id'] = $user->id;
            $attributes['status'] = TechnicalPlanStatus::Draft;
        }

        if ($submitting) {
            $attributes['status'] = TechnicalPlanStatus::Submitted;
            $attributes['submitted_at'] = now();
        }

        $plan->fill($attributes)->save();

        $plan->syncAttachments($data['extra']['files'] ?? []);
        $plan->syncSceneSoundFiles();

        Log::info($submitting ? 'Technical plan submitted' : 'Technical plan saved as a draft', [
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'performance_id' => $plan->performance_id,
            'status' => $plan->status->value,
            'created' => $wasNew,
            'scenes' => count($plan->scenes),
            'attachments' => count($data['extra']['files'] ?? []),
        ]);

        return $plan;
    }
}
