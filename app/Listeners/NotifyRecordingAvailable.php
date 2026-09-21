<?php

namespace App\Listeners;

use App\Events\PerformanceRecordingLinked;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\User;
use App\Notifications\PerformanceRecordingAvailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Tell whoever wrote a night's technical plan that there is now a video of it.
 *
 * Once ever, however many times the address is corrected afterwards. The claim
 * on that is made in one statement rather than by reading and then writing:
 * this is queued, and a job that crashed between sending the letters and
 * recording that it had would otherwise send them all again on the retry.
 *
 * Separate from {@see SyncPerformanceToJellyfin} on purpose — a media server
 * that is down should not hold back a letter about a video that is plainly up.
 * The audit entry is filed here for the same reason it is filed there: only
 * this class knows who was written to.
 */
class NotifyRecordingAvailable implements ShouldQueue
{
    /**
     * A recording put aside between the link being written and this running is
     * one nobody should be written to about.
     */
    public bool $deleteWhenMissingModels = true;

    public function handle(PerformanceRecordingLinked $event): void
    {
        $recording = $event->recording;

        $claimed = PerformanceRecording::query()
            ->whereKey($recording->getKey())
            ->whereNull('announced_at')
            ->update(['announced_at' => now()]);

        // A statement rather than a model save: this is bookkeeping, and an
        // audit entry saying the system changed a timestamp would bury the one
        // entry worth reading.
        if ($claimed === 0) {
            Log::info('A linked recording was already announced; no mail sent', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
            ]);

            return;
        }

        $performance = $recording->performance;

        if ($performance === null) {
            Log::warning('A linked recording has no performance to announce', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
            ]);

            return;
        }

        $authors = $this->authors($performance);

        if ($authors->isEmpty()) {
            Log::warning('A linked recording has no technical plan author to write to', [
                'recording_id' => $recording->id,
                'performance_id' => $performance->getKey(),
            ]);

            return;
        }

        Notification::send($authors, new PerformanceRecordingAvailable($recording));

        Log::info('Mailed out a linked recording', [
            'recording_id' => $recording->id,
            'performance_id' => $performance->getKey(),
            'recipients' => $authors->count(),
        ]);

        activity()
            ->performedOn($recording)
            ->causedBy($event->linkedBy)
            ->event('recording_announced')
            ->withProperties(['recipients' => $authors->count()])
            ->log(sprintf(
                'Performance recording announced to %d plan %s',
                $authors->count(),
                $authors->count() === 1 ? 'author' : 'authors',
            ));
    }

    /**
     * Who wrote the plans for this night, each of them once.
     *
     * A plan may carry no author — one filled in ahead of any account, or one
     * whose author has since been removed — and one person may have written
     * two of them, which is still one letter.
     *
     * @return Collection<int, User>
     */
    private function authors(Performance $performance): Collection
    {
        return $performance->technicalPlans()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
    }
}
