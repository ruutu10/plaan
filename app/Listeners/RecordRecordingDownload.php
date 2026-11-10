<?php

namespace App\Listeners;

use App\Events\PerformanceRecordingDownloadStarted;
use Illuminate\Support\Facades\Log;

/**
 * File who took a copy of a recording: once it has left the library, the audit
 * trail is the only record of where it went.
 */
class RecordRecordingDownload
{
    public function handle(PerformanceRecordingDownloadStarted $event): void
    {
        Log::info('A performance recording download started', [
            'recording_id' => $event->recording->id,
            'performance_id' => $event->recording->performance_id,
            'user_id' => $event->downloadedBy->id,
            'ip' => $event->ipAddress,
        ]);

        activity()
            ->performedOn($event->recording)
            ->causedBy($event->downloadedBy)
            ->event('recording_downloaded')
            ->withProperties(['ip' => $event->ipAddress])
            ->log(sprintf('Performance recording downloaded by %s', $event->downloadedBy->name));
    }
}
