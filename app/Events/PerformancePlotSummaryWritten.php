<?php

namespace App\Events;

use App\Listeners\SyncPerformanceToJellyfin;
use App\Models\PerformanceRecording;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A recording's plot summary changed — the model wrote one, or the crew
 * corrected it — and the episode's description wants pushing again. See
 * {@see SyncPerformanceToJellyfin}.
 */
class PerformancePlotSummaryWritten
{
    use Dispatchable, SerializesModels;

    /**
     * @param  User|null  $writtenBy  Who corrected it, or null for the model.
     */
    public function __construct(
        public PerformanceRecording $recording,
        public ?User $writtenBy = null,
    ) {
        //
    }
}
