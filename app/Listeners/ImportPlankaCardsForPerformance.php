<?php

namespace App\Listeners;

use App\Console\Commands\ImportPlankaPerformances;
use App\Events\PlankaReimportRequested;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Run the Planka import narrowed to the cards one performance was announced on.
 *
 * The work is the ordinary {@see ImportPlankaPerformances} command with its
 * title filter set, so a re-read by hand and the nightly run do exactly the same
 * thing to the books — a card is still matched to what is already recorded, and
 * a record put aside here is still left alone.
 *
 * Queued, because the run reads the board and asks the extraction model about
 * every card it keeps; nobody waits at a browser for that.
 */
class ImportPlankaCardsForPerformance implements ShouldBeUnique, ShouldQueue
{
    /**
     * How long one request holds the floor. Long enough to cover a slow reading
     * of the board, short enough that a run lost with its worker does not lock
     * the same title out for the rest of the day.
     */
    public int $uniqueFor = 900;

    /**
     * Asking twice for the same cards is asking for the same answer, so an
     * impatient second press joins the first rather than starting a second run
     * — and two performances of one shared evening ask about one title between
     * them.
     */
    public function uniqueId(PlankaReimportRequested $event): string
    {
        return mb_strtolower($event->filterTitle);
    }

    public function handle(PlankaReimportRequested $event): void
    {
        Log::info('Re-reading the Planka board for one performance', [
            'performance_id' => $event->performance->id,
            'filter_title' => $event->filterTitle,
            'requested_by' => $event->requestedBy->id,
        ]);

        // The command says everything else about what it did, to the log the
        // nightly run writes to; its own output has nowhere to go from here.
        Artisan::call('planka:import', ['--filter-title' => $event->filterTitle]);
    }
}
