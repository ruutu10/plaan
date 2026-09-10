<?php

namespace App\Listeners;

use App\Events\TechnicalPlanPerformanceChanged;
use App\Models\Performance;

/**
 * Keep an audit-trail entry for every plan moved to a different night, and who
 * moved it there — always a signed-in technician, since this only fires from
 * the plan's details page.
 */
class LogTechnicalPlanPerformanceChanged
{
    public function handle(TechnicalPlanPerformanceChanged $event): void
    {
        activity()
            ->performedOn($event->plan)
            ->causedBy($event->changedBy)
            ->event('performance_changed')
            ->withProperties([
                'from' => $event->previousPerformance?->getKey(),
                'to' => $event->newPerformance->getKey(),
            ])
            ->log(sprintf(
                'Technical plan moved from %s to %s by %s',
                $this->name($event->previousPerformance),
                $this->name($event->newPerformance),
                $event->changedBy->name,
            ));
    }

    /**
     * The night as a line worth reading a year from now: what is played and
     * when, rather than an id nothing can be looked up by once the performance
     * itself is gone.
     */
    private function name(?Performance $performance): string
    {
        if ($performance === null) {
            return 'no performance';
        }

        return $performance->displayName().' ('.$performance->startsAt()->format('d.m.Y').')';
    }
}
