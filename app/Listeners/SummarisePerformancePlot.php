<?php

namespace App\Listeners;

use App\Events\PerformancePlotSummaryRequested;
use App\Events\PerformancePlotSummaryWritten;
use App\Services\JellyfinClient;
use App\Services\PerformancePlotSummariser;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read a recording's subtitles and write up what happened on stage, once they
 * are there.
 *
 * The library's speech-to-text takes its time over a new video, so "no subtitles
 * yet" is the usual answer and not a failure: the recording is left waiting
 * and the next daily run asks again. Once they are in, the transcript goes to
 * {@see PerformancePlotSummariser}, and what comes back is kept on the
 * recording and pushed onto the episode by {@see SyncPerformanceToJellyfin}.
 *
 * A failure reaching the library or the model is thrown on, for the queue to
 * retry; a recording whose attempts are all spent is still waiting, so the next
 * daily run picks it up again. The design is in docs/adr/0001.
 */
class SummarisePerformancePlot implements ShouldBeUnique, ShouldQueue
{
    public bool $deleteWhenMissingModels = true;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $tries = 4;

    /**
     * Long enough to cover a slow model over a two-hour transcript.
     */
    public int $uniqueFor = 1800;

    public function __construct(
        protected JellyfinClient $jellyfin,
        protected PerformancePlotSummariser $summariser,
    ) {}

    /**
     * One reading of a recording at a time: a press of "regenerate" landing
     * on top of the daily run joins it rather than paying for the same answer
     * twice.
     */
    public function uniqueId(PerformancePlotSummaryRequested $event): string
    {
        return (string) $event->recording->getKey();
    }

    public function handle(PerformancePlotSummaryRequested $event): void
    {
        $recording = $event->recording;
        $performance = $recording->performance;

        if (! JellyfinClient::isConfigured() || blank(config('services.anthropic.key'))) {
            Log::info('No Jellyfin or Claude configured; a plot summary was not written', [
                'recording_id' => $recording->id,
            ]);

            return;
        }

        // Settled since it was asked for: written by hand, or a run before this
        // one got there first. A fresh request has already cleared the markers,
        // and keeps the text it is replacing on screen until then.
        $settled = $recording->plot_summary_generated_at !== null
            || $recording->plot_summary_abandoned_at !== null
            || (! $event->isFreshRequest() && $recording->plot_summary !== null);

        if ($performance === null || blank($recording->item_id) || $settled) {
            return;
        }

        $context = [
            'recording_id' => $recording->id,
            'performance_id' => $recording->performance_id,
            'item_id' => $recording->item_id,
        ];

        $subtitle = $this->jellyfin->estonianSubtitle($recording->item_id);

        if ($subtitle === null) {
            Log::debug('No Estonian subtitles on a recording yet', $context);

            return;
        }

        $srt = $this->jellyfin->subtitleText($recording->item_id, $subtitle['mediaSourceId'], $subtitle['index']);

        $summariser = $event->isFreshRequest() ? $this->summariser->withoutClaudeCache() : $this->summariser;

        $reading = $summariser->summarise($performance, $srt);

        if (! $reading->isUsable()) {
            $recording->forceFill(['plot_summary_abandoned_at' => now()])->save();

            Log::warning('A recording\'s subtitles held no readable plot', [...$context, 'reason' => $reading->reason]);

            activity()
                ->performedOn($recording)
                ->causedBy($event->requestedBy)
                ->event('plot_summary_abandoned')
                ->withProperties(['reason' => $reading->reason])
                ->log(sprintf('No plot summary could be read from the subtitles of %s', $performance->displayName()));

            return;
        }

        $recording->forceFill([
            'plot_summary' => $reading->summary,
            'plot_tags' => $reading->tags,
            'plot_summary_generated_at' => now(),
        ])->save();

        activity()
            ->performedOn($recording)
            ->causedBy($event->requestedBy)
            ->event('plot_summary_generated')
            ->withProperties(['subtitle_index' => $subtitle['index'], 'tags' => $reading->tags])
            ->log(sprintf('Plot summary written from the subtitles of %s', $performance->displayName()));

        PerformancePlotSummaryWritten::dispatch($recording);
    }

    /**
     * Every attempt is spent. The recording is still waiting, so tomorrow's run
     * tries again; this is said out loud so a failure that keeps happening is
     * seen.
     */
    public function failed(PerformancePlotSummaryRequested $event, Throwable $e): void
    {
        Log::error('Gave up writing a plot summary for a recording', [
            'recording_id' => $event->recording->id,
            'performance_id' => $event->recording->performance_id,
            'exception' => $e->getMessage(),
        ]);

        activity()
            ->performedOn($event->recording)
            ->causedBy($event->requestedBy)
            ->event('plot_summary_failed')
            ->withProperties(['error' => $e->getMessage()])
            ->log('A plot summary could not be written');
    }
}
