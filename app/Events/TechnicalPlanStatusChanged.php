<?php

namespace App\Events;

use App\Enums\TechnicalPlanStatus;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A plan moved from one status to another, at a signed-in user's hand — fired
 * for every transition the admin overview makes, whether or not anything
 * currently reacts to that particular pair of statuses.
 */
class TechnicalPlanStatusChanged
{
    use Dispatchable;

    public function __construct(
        public TechnicalPlan $plan,
        public TechnicalPlanStatus $previousStatus,
        public TechnicalPlanStatus $newStatus,
        public User $changedBy,
    ) {
        //
    }
}
