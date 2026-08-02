<?php

namespace App\Listeners;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Notifications\TechnicalPlanReceived;
use Illuminate\Support\Facades\Log;

/**
 * Tell a plan's author that the technical team has picked it up — the one
 * transition, submitted to received, that earns a letter. Every other
 * transition a plan passes through is not this listener's concern. A plan
 * with no author — filled in ahead of any account, or one whose author has
 * since been removed — has nobody to tell.
 */
class NotifyTechnicalPlanReceived
{
    public function handle(TechnicalPlanStatusChanged $event): void
    {
        if ($event->previousStatus !== TechnicalPlanStatus::Submitted || $event->newStatus !== TechnicalPlanStatus::Received) {
            return;
        }

        $plan = $event->plan;

        if ($plan->user === null) {
            Log::info('A plan was marked received but has no author to notify', [
                'plan_id' => $plan->id,
            ]);

            return;
        }

        $plan->user->notify(new TechnicalPlanReceived($plan, $event->changedBy));

        Log::info('Mailed a plan\'s author that it was received', [
            'plan_id' => $plan->id,
            'confirmed_by' => $event->changedBy->id,
        ]);
    }
}
