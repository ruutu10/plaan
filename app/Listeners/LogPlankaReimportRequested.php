<?php

namespace App\Listeners;

use App\Events\PlankaReimportRequested;

/**
 * Keep an audit-trail entry for somebody asking for a performance to be read
 * off its Planka card again.
 *
 * Synchronous, unlike {@see ImportPlankaCardsForPerformance}: the run itself
 * happens on a queue, where whatever it changes is filed as the system's doing,
 * so this entry is the one that says who set it off.
 */
class LogPlankaReimportRequested
{
    public function handle(PlankaReimportRequested $event): void
    {
        activity()
            ->performedOn($event->performance)
            ->causedBy($event->requestedBy)
            ->event('planka_reimport_requested')
            ->withProperties(['card_id' => $event->cardId])
            ->log(sprintf('Planka re-import requested by %s', $event->requestedBy->name));
    }
}
