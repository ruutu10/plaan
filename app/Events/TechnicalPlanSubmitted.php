<?php

namespace App\Events;

use App\Enums\TechnicalPlanStatus;
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

    /**
     * @param  TechnicalPlanStatus|null  $previousStatus  The status the plan
     *                                                    held before this save, or null when the save created it.
     */
    public function __construct(
        public TechnicalPlan $plan,
        public ?TechnicalPlanStatus $previousStatus = null,
    ) {
        //
    }

    /**
     * Whether this submission is an update to a plan the technical team
     * already holds — one submitted before, or one they have since confirmed.
     */
    public function isResubmission(): bool
    {
        return $this->previousStatus !== null
            && in_array($this->previousStatus, TechnicalPlanStatus::delivered(), true);
    }
}
