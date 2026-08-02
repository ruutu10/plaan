<?php

namespace App\Actions;

use App\Models\TechnicalPlan;
use App\Notifications\TechnicalPlanReceived;
use Illuminate\Support\Facades\Log;

/**
 * Tell a plan's author that the technical team has picked it up, once its
 * status moves from submitted to received. A plan with no author — filled in
 * ahead of any account, or one whose author has since been removed — has
 * nobody to tell.
 */
class NotifyPlanReceived
{
    public function handle(TechnicalPlan $plan): void
    {
        if ($plan->user === null) {
            Log::info('A plan was marked received but has no author to notify', [
                'plan_id' => $plan->id,
            ]);

            return;
        }

        $plan->user->notify(new TechnicalPlanReceived($plan));

        Log::info('Mailed a plan\'s author that it was received', [
            'plan_id' => $plan->id,
        ]);
    }
}
