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

        $blindCopies = $this->blindCopies($performance, $authors);

        // The blind copies ride on one letter and one only: a shared evening
        // has a plan per act, and the people on stage should not be sent the
        // same news once per plan somebody else wrote.
        foreach ($authors as $index => $author) {
            $author->notify(new PerformanceRecordingAvailable(
                $recording,
                $index === 0 ? $blindCopies : [],
            ));
        }

        Log::info('Mailed out a linked recording', [
            'recording_id' => $recording->id,
            'performance_id' => $performance->getKey(),
            'recipients' => $authors->count(),
            'blind_copies' => count($blindCopies),
        ]);

        activity()
            ->performedOn($recording)
            ->causedBy($event->linkedBy)
            ->event('recording_announced')
            ->withProperties([
                'recipients' => $authors->count(),
                'blind_copies' => count($blindCopies),
            ])
            ->log(sprintf(
                'Performance recording announced to %d plan %s and %d on stage',
                $authors->count(),
                $authors->count() === 1 ? 'author' : 'authors',
                count($blindCopies),
            ));
    }

    /**
     * The addresses of whoever was on stage that night, for the blind copy.
     *
     * Anybody already being written to openly is left out: an author who also
     * played should get the letter once, addressed to them, rather than twice.
     * A night the import has read no cast for leaves this empty, and the letter
     * goes to its author alone.
     *
     * @param  Collection<int, User>  $authors
     * @return array<int, string>
     */
    private function blindCopies(Performance $performance, Collection $authors): array
    {
        $addressed = $authors->pluck('email')->all();

        return $performance->onStage()
            ->get()
            ->pluck('email')
            ->filter()
            ->unique()
            ->reject(fn (string $email): bool => in_array($email, $addressed, true))
            ->values()
            ->all();
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
