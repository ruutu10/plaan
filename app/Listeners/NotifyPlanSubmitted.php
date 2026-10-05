<?php

namespace App\Listeners;

use App\Events\TechnicalPlanSubmitted as TechnicalPlanSubmittedEvent;
use App\Models\TechnicalPlan;
use App\Notifications\TechnicalPlanSubmitted as TechnicalPlanSubmittedNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Mail a submitted plan out: the performer keeps a copy of what they sent, and
 * the technical team gets the plan they will run the format from. The night's
 * performers, as the performance's staff names them, are blind-copied on the
 * author's letter: the plan is written on their behalf too.
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

        $techEmail = (string) config('technical_plan.tech_email');
        $notifiedTech = $techEmail !== '' && $techEmail !== $plan->user?->email;

        $blindCopies = $plan->user === null ? [] : $this->blindCopies($plan, [$plan->user->email, $techEmail]);

        // The performers ride on the author's letter alone, so each of them
        // gets the plan once rather than once per address it was sent to.
        $plan->user?->notify(new TechnicalPlanSubmittedNotification($plan, $blindCopies));

        if ($notifiedTech) {
            Notification::route('mail', $techEmail)->notify($notification);
        }

        // A submitted plan the technical team never received is the failure
        // that costs a format, so who was mailed is recorded either way.
        Log::info('Mailed out a submitted plan', [
            'plan_id' => $plan->id,
            'notified_owner' => $plan->user !== null,
            'notified_tech' => $notifiedTech,
            'blind_copies' => count($blindCopies),
        ]);

        if ($techEmail === '') {
            Log::warning('No technical contact configured; a submitted plan reached nobody but its author', [
                'plan_id' => $plan->id,
            ]);
        }
    }

    /**
     * The addresses of the night's performers, for the blind copy.
     *
     * Anybody already being written to openly is left out: a performer who
     * wrote the plan themselves should get it once, addressed to them, rather
     * than twice. A plan for no performance, or a night the import has read no
     * cast for, leaves this empty.
     *
     * @param  array<int, string>  $addressed
     * @return array<int, string>
     */
    private function blindCopies(TechnicalPlan $plan, array $addressed): array
    {
        if ($plan->performance === null) {
            return [];
        }

        return $plan->performance->performers()
            ->get()
            ->pluck('email')
            ->filter()
            ->unique()
            ->reject(fn (string $email): bool => in_array($email, $addressed, true))
            ->values()
            ->all();
    }
}
