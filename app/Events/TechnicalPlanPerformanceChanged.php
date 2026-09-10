<?php

namespace App\Events;

use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A plan was moved to a different night, at a technician's hand from the plan's
 * details page. The usual reason is a plan written under the stand-in
 * performance — see {@see Performance::placeholder()} — once the real evening
 * has made it onto the books.
 */
class TechnicalPlanPerformanceChanged
{
    use Dispatchable;

    /**
     * @param  Performance|null  $previousPerformance  The night the plan was filed under, or null when it had since been put aside.
     */
    public function __construct(
        public TechnicalPlan $plan,
        public ?Performance $previousPerformance,
        public Performance $newPerformance,
        public User $changedBy,
    ) {
        //
    }
}
