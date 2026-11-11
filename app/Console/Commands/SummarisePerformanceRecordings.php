<?php

namespace App\Console\Commands;

use App\Events\PerformancePlotSummaryRequested;
use App\Listeners\SummarisePerformancePlot;
use App\Models\PerformanceRecording;
use App\Services\JellyfinClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Ask after every recording still waiting on a plot summary.
 *
 * Nothing is read or written here beyond the waiting list: each recording is
 * handed to {@see SummarisePerformancePlot}, queued, which looks for the
 * subtitles and writes the summary once they are in. A recording that has
 * waited longer than the window is given up on instead, so a video the
 * library never transcribes stops being asked about.
 */
#[Signature('performances:summarise-recordings
    {--days=30 : Give up on a recording whose subtitles have not appeared this many days after it was linked}
    {--dry-run : Name the recordings that would be asked after without queueing anything}')]
#[Description('Write plot summaries for recordings whose subtitles have arrived.')]
class SummarisePerformanceRecordings extends Command
{
    public function handle(): int
    {
        if (! JellyfinClient::isConfigured() || blank(config('services.anthropic.key'))) {
            $this->warn('Jellyfin or Claude is not configured; nothing to do.');

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $requested = 0;
        $abandoned = 0;

        PerformanceRecording::query()
            ->awaitingPlotSummary()
            ->with('performance.format')
            ->lazyById()
            ->each(function (PerformanceRecording $recording) use ($dryRun, $cutoff, &$requested, &$abandoned): void {
                $name = $recording->performance?->displayName() ?? '#'.$recording->performance_id;
                $linkedAt = $recording->linked_at ?? $recording->created_at;

                if ($linkedAt !== null && $linkedAt->lt($cutoff)) {
                    $this->line(sprintf('  %s %s', $dryRun ? 'Would give up on' : 'Giving up on', $name));

                    if (! $dryRun) {
                        $recording->forceFill(['plot_summary_abandoned_at' => now()])->save();

                        activity()
                            ->performedOn($recording)
                            ->event('plot_summary_abandoned')
                            ->withProperties(['reason' => 'no_subtitles', 'linked_at' => $linkedAt->toIso8601String()])
                            ->log(sprintf('Stopped waiting for subtitles of %s', $name));
                    }

                    $abandoned++;

                    return;
                }

                $this->line(sprintf('  %s %s', $dryRun ? 'Would ask after' : 'Asking after', $name));

                if (! $dryRun) {
                    PerformancePlotSummaryRequested::dispatch($recording);
                }

                $requested++;
            });

        $this->info(sprintf(
            '%s %d recording(s), %s %d.',
            $dryRun ? 'Would ask after' : 'Asked after',
            $requested,
            $dryRun ? 'would give up on' : 'gave up on',
            $abandoned,
        ));

        Log::info('Plot summary run finished', [
            'dry_run' => $dryRun,
            'requested' => $requested,
            'abandoned' => $abandoned,
            'window_days' => $days,
        ]);

        return self::SUCCESS;
    }
}
