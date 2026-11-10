<?php

namespace App\Events;

use App\Models\PerformanceRecording;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Somebody began taking a copy of a night's video out of the house's library.
 *
 * Fired once per download: a download picked up again after a dropped
 * connection is the same copy, and is not news.
 */
class PerformanceRecordingDownloadStarted
{
    use Dispatchable;

    public function __construct(
        public PerformanceRecording $recording,
        public User $downloadedBy,
        public ?string $ipAddress,
    ) {
        //
    }
}
