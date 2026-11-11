<?php

namespace App\Listeners;

use App\Events\PerformancePlotSummaryAbandoned;
use App\Events\PerformancePlotSummaryWritten;
use App\Events\PerformanceRecordingLinked;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\User;
use App\Notifications\PerformanceRecordingAvailable;
use App\Services\PerformancePlotSummariser;
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
 * A visiting performer's private address is written to as well, since the video
 * can be downloaded with nothing more than a login here. Their letter leaves out
 * the link into the library only the house can open (see
 * {@see User::isHouseStaff()}), so it cannot share a blind copy with the house's
 * and goes to each of them separately.
 *
 * The letter carries the night's plot summary and tags, so it waits for them
 * rather than going out the moment the link is pasted: it is sent when the
 * first summary is written, by the model or by hand. A recording that will
 * never have one is announced without it: one given up on (see
 * {@see PerformancePlotSummaryAbandoned}), or one linked where no summaries can
 * be written at all. The design is in docs/adr/0001.
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

    public function handle(PerformanceRecordingLinked|PerformancePlotSummaryWritten|PerformancePlotSummaryAbandoned $event): void
    {
        $recording = $event->recording;

        if (! $this->isTimeToAnnounce($event)) {
            Log::debug('A recording letter is waiting for its plot summary', [
                'recording_id' => $recording->id,
                'performance_id' => $recording->performance_id,
                'event' => class_basename($event),
            ]);

            return;
        }

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

        // The blind copies ride on one letter and one only: a shared evening
        // has a plan per act, and the people on stage should not be sent the
        // same news once per plan somebody else wrote. It has to be a house
        // author's letter, or the copies would lose the link into the library.
        $carrier = $authors->first(fn (User $author): bool => $author->isHouseStaff());

        [$blindlyCopied, $writtenToAlone] = $this->partitionOnStage($performance, $authors, $carrier);

        $blindCopies = $blindlyCopied->pluck('email')->all();

        foreach ($authors as $author) {
            $author->notify(new PerformanceRecordingAvailable(
                $recording,
                $author->is($carrier) ? $blindCopies : [],
            ));
        }

        foreach ($writtenToAlone as $member) {
            $member->notify(new PerformanceRecordingAvailable($recording));
        }

        Log::info('Mailed out a linked recording', [
            'recording_id' => $recording->id,
            'performance_id' => $performance->getKey(),
            'recipients' => $authors->count(),
            'blind_copies' => count($blindCopies),
            'separate_letters' => $writtenToAlone->count(),
        ]);

        activity()
            ->performedOn($recording)
            ->causedBy($this->causer($event))
            ->event('recording_announced')
            ->withProperties([
                'recipients' => $authors->count(),
                'blind_copies' => count($blindCopies),
                'separate_letters' => $writtenToAlone->count(),
            ])
            ->log(sprintf(
                'Performance recording announced to %d plan %s and %d on stage',
                $authors->count(),
                $authors->count() === 1 ? 'author' : 'authors',
                count($blindCopies) + $writtenToAlone->count(),
            ));
    }

    /**
     * Whether the letter can go out now, or should wait for the plot summary.
     *
     * A fresh link waits, unless a summary was written by hand in the same save,
     * or nothing here could ever write one. A summary written means the letter
     * can go, but only if there is text: a summary cleared by hand fires the same
     * event and leaves nothing to show. Once the house has given up on a summary,
     * the letter goes without one.
     */
    private function isTimeToAnnounce(PerformanceRecordingLinked|PerformancePlotSummaryWritten|PerformancePlotSummaryAbandoned $event): bool
    {
        $recording = $event->recording;

        return match (true) {
            $event instanceof PerformanceRecordingLinked => filled($recording->plot_summary)
                || blank($recording->item_id)
                || ! PerformancePlotSummariser::isConfigured(),
            $event instanceof PerformancePlotSummaryWritten => filled($recording->plot_summary),
            $event instanceof PerformancePlotSummaryAbandoned => true,
        };
    }

    /**
     * Who to put the announcement down to in the audit trail: whoever did what
     * set it off, or nobody for the model and the daily run.
     */
    private function causer(PerformanceRecordingLinked|PerformancePlotSummaryWritten|PerformancePlotSummaryAbandoned $event): ?User
    {
        return match (true) {
            $event instanceof PerformanceRecordingLinked => $event->linkedBy,
            $event instanceof PerformancePlotSummaryWritten => $event->writtenBy,
            $event instanceof PerformancePlotSummaryAbandoned => $event->requestedBy,
        };
    }

    /**
     * Whoever was on stage that night, split into those blind-copied on the
     * carrying letter and those written to on their own.
     *
     * Anybody already being written to openly is left out: an author who also
     * played should get the letter once, addressed to them, rather than twice.
     * With no house author there is no letter to ride on, so everybody is
     * written to separately.
     *
     * @param  Collection<int, User>  $authors
     * @return array{0: Collection<int, User>, 1: Collection<int, User>}
     */
    private function partitionOnStage(Performance $performance, Collection $authors, ?User $carrier): array
    {
        $addressed = $authors->pluck('email')->all();

        $onStage = $performance->onStage()
            ->get()
            ->filter(fn (User $member): bool => filled($member->email))
            ->unique('email')
            ->reject(fn (User $member): bool => in_array($member->email, $addressed, true))
            ->values()
            ->toBase();

        if ($carrier === null) {
            return [new Collection, $onStage];
        }

        return [
            $onStage->filter(fn (User $member): bool => $member->isHouseStaff())->values(),
            $onStage->reject(fn (User $member): bool => $member->isHouseStaff())->values(),
        ];
    }

    /**
     * Who wrote the approved plans for this night, each of them once. Anybody
     * can file a plan against any night, so an unapproved one earns no letter.
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
            ->approved()
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn (?User $author): bool => filled($author?->email))
            ->unique('id')
            ->values();
    }
}
