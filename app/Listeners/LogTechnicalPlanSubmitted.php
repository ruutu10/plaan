<?php

namespace App\Listeners;

use App\Events\TechnicalPlanSubmitted as TechnicalPlanSubmittedEvent;
use App\Models\TechnicalPlan;

/**
 * Keep an audit-trail entry for a plan being submitted — freshly, or
 * resubmitted after edits — distinct from the routine draft saves the wizard
 * makes along the way, which {@see TechnicalPlan} does not log
 * automatically.
 */
class LogTechnicalPlanSubmitted
{
    public function handle(TechnicalPlanSubmittedEvent $event): void
    {
        $actor = auth()->user();

        activity()
            ->performedOn($event->plan)
            ->event('submitted')
            ->withProperties(['performance_id' => $event->plan->performance_id])
            // @phpstan-ignore-next-line nullsafe.neverNull (see App\Concerns\LogsModelActivity::activityDescription())
            ->log(sprintf('Technical plan submitted by %s', $actor?->name ?? 'the system'));
    }
}
