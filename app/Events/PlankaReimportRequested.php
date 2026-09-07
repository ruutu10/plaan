<?php

namespace App\Events;

use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Somebody asked for one performance to be read off the Planka board again,
 * because the card has moved on and the record here has not.
 *
 * Fired rather than run on the spot: the reading calls the extraction model and
 * walks the board, which is far too slow to keep a request waiting on — see
 * {@see App\Listeners\ImportPlankaCardsForPerformance}, which is queued.
 */
class PlankaReimportRequested
{
    /**
     * The listener is queued, so what travels to it is three model keys rather
     * than three models: whoever presses the button has the performance and its
     * format in hand, and a run minutes later should read them as they are then.
     */
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $filterTitle  The card titles the run is narrowed to — see
     *                               {@see Performance::plankaImportFilter()}.
     */
    public function __construct(
        public Performance $performance,
        public string $filterTitle,
        public User $requestedBy,
    ) {
        //
    }
}
