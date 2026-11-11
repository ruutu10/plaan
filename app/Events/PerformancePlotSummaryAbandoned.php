<?php

namespace App\Events;

use App\Listeners\NotifyRecordingAvailable;
use App\Listeners\SummarisePerformancePlot;
use App\Models\PerformanceRecording;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The house stopped waiting for a recording's plot summary: its subtitles never
 * appeared inside the window, or {@see SummarisePerformancePlot} found nothing
 * readable in them.
 *
 * The letter to the performers waits on the summary, so this is what lets it
 * go without one — see {@see NotifyRecordingAvailable}.
 */
class PerformancePlotSummaryAbandoned
{
    use Dispatchable, SerializesModels;

    /**
     * @param  User|null  $requestedBy  Who asked for the reading that gave up, or null for the daily run.
     */
    public function __construct(
        public PerformanceRecording $recording,
        public ?User $requestedBy = null,
    ) {
        //
    }
}
