<?php

namespace App\Listeners;

use App\Events\TechnicalPlanStatusChanged;

/**
 * Keep an audit-trail entry for every status a plan passes through, and who
 * moved it there — always a signed-in technician, since this only fires from
 * the admin overview.
 */
class LogTechnicalPlanStatusChanged
{
    public function handle(TechnicalPlanStatusChanged $event): void
    {
        activity()
            ->performedOn($event->plan)
            ->causedBy($event->changedBy)
            ->event('status_changed')
            ->withProperties([
                'from' => $event->previousStatus->value,
                'to' => $event->newStatus->value,
            ])
            ->log(sprintf(
                'Technical plan status changed from %s to %s by %s',
                $event->previousStatus->value,
                $event->newStatus->value,
                $event->changedBy->name,
            ));
    }
}
