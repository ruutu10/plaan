<?php

namespace App\Listeners;

use App\Events\TechnicalPlanSubmitted as TechnicalPlanSubmittedEvent;
use App\Notifications\TechnicalPlanSubmitted as TechnicalPlanSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Mail a submitted plan out: the performer keeps a copy of what they sent, and
 * the technical team gets the plan they will run the format from.
 *
 * Only the first submission is mailed. A plan the team already holds — one
 * submitted before, or one a technician has since confirmed — is resubmitted
 * from the wizard as a matter of course while the performer keeps tidying it
 * up, and a letter for every pass is noise the crew learns to ignore. The
 * plan's own link always opens the current version, so the mail they were sent
 * stays a way in to what the plan says now.
 */
class NotifyPlanSubmitted
{
    public function handle(TechnicalPlanSubmittedEvent $event): void
    {
        $plan = $event->plan;

        if ($event->isResubmission()) {
            Log::info('An already-submitted plan was updated; no mail sent', [
                'plan_id' => $plan->id,
                'previous_status' => $event->previousStatus->value,
            ]);

            return;
        }

        $notification = new TechnicalPlanSubmittedNotification($plan);

        $plan->user?->notify($notification);

        $techEmail = (string) config('technical_plan.tech_email');
        $notifiedTech = $techEmail !== '' && $techEmail !== $plan->user?->email;

        if ($notifiedTech) {
            Notification::route('mail', $techEmail)->notify($notification);
        }

        // A submitted plan the technical team never received is the failure
        // that costs a format, so who was mailed is recorded either way.
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
