<?php

namespace App\Events;

use App\Listeners\SummarisePerformancePlot;
use App\Models\PerformanceRecording;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A recording still wants a plot summary: see whether its subtitles are in yet,
 * and write one up if they are.
 *
 * Fired by the daily run for every recording still waiting, with nobody behind
 * it, and by a crew member asking for a fresh summary of one — see
 * {@see SummarisePerformancePlot}, which is queued.
 */
class PerformancePlotSummaryRequested
{
    /**
     * The listener is queued and should read the recording as it stands when
     * it runs: a summary written by hand in the meantime wins.
     */
    use Dispatchable, SerializesModels;

    /**
     * @param  User|null  $requestedBy  Who asked, or null for the daily run.
     */
    public function __construct(
        public PerformanceRecording $recording,
        public ?User $requestedBy = null,
    ) {
        //
    }

    /**
     * Whether somebody asked for this one by hand, which is asking for the
     * model's own reading again rather than last time's answer.
     */
    public function isFreshRequest(): bool
    {
        return $this->requestedBy !== null;
    }
}
