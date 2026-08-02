<?php

namespace App\Events;

use App\Models\TechnicalPlan;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A plan was submitted — freshly, or resubmitted after edits. Fired the
 * moment the save that marks it Submitted completes, so mailing it out is a
 * listener's concern rather than the controller's.
 */
class TechnicalPlanSubmitted
{
    use Dispatchable;

    public function __construct(
        public TechnicalPlan $plan,
    ) {
        //
    }
}
