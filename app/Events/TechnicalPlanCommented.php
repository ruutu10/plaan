<?php

namespace App\Events;

use App\Listeners\NotifyPlanCommented;
use App\Models\TechnicalPlanComment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody said something on a plan. Fired for every comment, whichever side of
 * the conversation wrote it — who is told about it is the listeners' question,
 * see {@see NotifyPlanCommented}.
 */
class TechnicalPlanCommented
{
    use Dispatchable;

    public function __construct(public TechnicalPlanComment $comment) {}
}
