<?php

namespace App\Actions;

use App\Models\TechnicalPlan;
use App\Notifications\TechnicalPlanSubmitted;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Mail a submitted plan out: the performer keeps a copy of what they sent, and
 * the technical team gets the plan they will run the show from. Resubmitting
 * notifies again — the plan the team holds has to be the current one.
 */
class NotifyPlanSubmitted
{
    public function handle(TechnicalPlan $plan): void
    {
        $notification = new TechnicalPlanSubmitted($plan);

        $plan->user?->notify($notification);

        $techEmail = (string) config('technical_plan.tech_email');
        $notifiedTech = $techEmail !== '' && $techEmail !== $plan->user?->email;

        if ($notifiedTech) {
            Notification::route('mail', $techEmail)->notify($notification);
        }

        // A submitted plan the technical team never received is the failure
        // that costs a show, so who was mailed is recorded either way.
        Log::info('Mailed out a submitted plan', [
            'plan_id' => $plan->id,
            'notified_owner' => $plan->user !== null,
            'notified_tech' => $notifiedTech,
        ]);

        if ($techEmail === '') {
            Log::warning('No technical contact configured; a submitted plan reached nobody but its author', [
                'plan_id' => $plan->id,
            ]);
        }
    }
}
