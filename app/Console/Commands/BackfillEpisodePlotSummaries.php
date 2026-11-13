<?php

namespace App\Console\Commands;

use App\Data\PlotSummary;
use App\Services\JellyfinClient;
use App\Services\PerformancePlotSummariser;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Write plot summaries onto library episodes that have subtitles and no
 * description — the recordings from before this app, or never linked to it.
 *
 * Jellyfin is the only source and the only destination: no database row is
 * read or written. Each episode is read, summarised and written back one at a
 * time, in the foreground, so a long run can be watched and stopped. The
 * summary becomes the Overview, and its tags join the episode's own.
 *
 * An episode with any Overview at all is left alone, which is also what makes
 * a second run pick up only where the first one did not get to.
 *
 * Given `--id`, only that one episode is summarised, and its description and
 * tags are replaced even if it already has them — the way to redo one.
 */
#[Signature('performances:summarise-backfill
    {--id= : Summarise only the episode with this Jellyfin item ID, replacing its description and tags}
    {--dry-run : List the episodes that would be summarised without asking the model or writing anything}')]
#[Description('Write plot summaries into Jellyfin for episodes with subtitles and an empty description.')]
class BackfillEpisodePlotSummaries extends Command
{
    public function handle(JellyfinClient $jellyfin, PerformancePlotSummariser $summariser): int
    {
        if (! PerformancePlotSummariser::isConfigured()) {
            $this->warn('Jellyfin or Claude is not configured; nothing to do.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (filled($this->option('id'))) {
            return $this->backfillOne($jellyfin, $summariser, (string) $this->option('id'), $dryRun);
        }

        /** @var array{episodes: int, described: int, no_subtitles: int, summarised: int, unusable: int, failed: int} $counts */
        $counts = ['episodes' => 0, 'described' => 0, 'no_subtitles' => 0, 'summarised' => 0, 'unusable' => 0, 'failed' => 0];

        foreach ($jellyfin->allSeries() as $series) {
            $seriesId = (string) ($series['Id'] ?? '');

            if ($seriesId === '') {
                continue;
            }

            $this->line(sprintf('<comment>%s</comment>', $series['Name'] ?? $seriesId));

            foreach ($jellyfin->episodesOf($seriesId) as $episode) {
                $counts['episodes']++;
                $counts[$this->backfill($jellyfin, $summariser, $episode, $dryRun)]++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%d episode(s): %s %d, %d unreadable, %d failed; skipped %d with a description and %d without subtitles.',
            $counts['episodes'],
            $dryRun ? 'would summarise' : 'summarised',
            $counts['summarised'],
            $counts['unusable'],
            $counts['failed'],
            $counts['described'],
            $counts['no_subtitles'],
        ));

        Log::info('Episode plot summary backfill finished', ['dry_run' => $dryRun, ...$counts]);

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The one episode named by `--id`, written over whatever description and
     * tags it already has.
     */
    private function backfillOne(JellyfinClient $jellyfin, PerformancePlotSummariser $summariser, string $itemId, bool $dryRun): int
    {
        try {
            $episode = $jellyfin->episode($itemId);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $outcome = $this->backfill($jellyfin, $summariser, $episode, $dryRun, overwrite: true);

        Log::info('Episode plot summary backfill finished', ['dry_run' => $dryRun, 'item_id' => $itemId, 'outcome' => $outcome]);

        return $outcome === 'failed' || $outcome === 'no_subtitles' ? self::FAILURE : self::SUCCESS;
    }

    /**
     * One episode, start to finish. Answers with the tally it counts towards.
     *
     * A failure is reported and counted rather than thrown, so one broken
     * episode does not stop the rest of the library being filled in.
     *
     * @param  array<string, mixed>  $episode
     * @param  bool  $overwrite  Summarise even a described episode, and replace its tags rather than add to them.
     * @return 'described'|'no_subtitles'|'summarised'|'unusable'|'failed'
     */
    private function backfill(JellyfinClient $jellyfin, PerformancePlotSummariser $summariser, array $episode, bool $dryRun, bool $overwrite = false): string
    {
        $itemId = (string) ($episode['Id'] ?? '');
        $name = (string) ($episode['Name'] ?? $itemId);

        if (! $overwrite && filled($episode['Overview'] ?? null)) {
            $this->line(sprintf('  · %s — has a description, skipped', $name), verbosity: 'v');

            return 'described';
        }

        $subtitle = JellyfinClient::estonianSubtitleOf($episode);

        if ($subtitle === null) {
            $this->line(sprintf('  · %s — no Estonian subtitles, skipped', $name), verbosity: $overwrite ? 'normal' : 'v');

            return 'no_subtitles';
        }

        if ($dryRun) {
            $this->line(sprintf('  → %s — would summarise', $name));

            return 'summarised';
        }

        $this->line(sprintf('  → %s — summarising… <fg=gray>(%s)</>', $name, $itemId));

        try {
            $srt = $jellyfin->subtitleText($itemId, $subtitle['mediaSourceId'], $subtitle['index']);

            $reading = $summariser->summariseNight(
                $this->detailsOf($episode),
                $this->onStageOf($episode),
                $srt,
                ['item_id' => $itemId],
            );

            if (! $reading->isUsable()) {
                $this->warn(sprintf('    unreadable: %s', $reading->reason ?? 'no reason given'));

                return 'unusable';
            }

            $jellyfin->applyToItem($itemId, $this->patchFor($episode, $reading, $overwrite));
        } catch (Throwable $e) {
            // The exception message cuts an HTTP answer short, and Jellyfin
            // names the field it refused at the end of it — so the body goes
            // to the log whole.
            $body = $e instanceof RequestException ? $e->response->body() : null;

            $this->error(sprintf('    failed: %s', $body ?? $e->getMessage()));

            Log::error('Could not backfill a plot summary onto an episode', [
                'item_id' => $itemId,
                'exception' => $e->getMessage(),
                'status' => $e instanceof RequestException ? $e->response->status() : null,
                'response_body' => $body,
            ]);

            return 'failed';
        }

        $this->line(sprintf('    <info>written</info>, tags: %s', $reading->tags === [] ? '—' : implode(', ', $reading->tags)));

        // The summary itself, indented under its episode, for review as it runs.
        foreach (explode("\n", trim((string) $reading->summary)) as $paragraph) {
            $this->line('    │ '.$paragraph);
        }

        $this->newLine();

        return 'summarised';
    }

    /**
     * What the library says about the night, in the lines the prompt expects
     * — standing in for what this app would know of it.
     *
     * @param  array<string, mixed>  $episode
     * @return list<string>
     */
    private function detailsOf(array $episode): array
    {
        $date = is_string($episode['PremiereDate'] ?? null)
            ? CarbonImmutable::parse($episode['PremiereDate'])->toDateString()
            : null;

        return array_values(array_filter([
            filled($episode['SeriesName'] ?? null) ? 'Formaat: '.$episode['SeriesName'] : null,
            filled($episode['Name'] ?? null) ? 'Episood: '.$episode['Name'] : null,
            $date !== null ? 'Kuupäev: '.$date : null,
        ]));
    }

    /**
     * Who the library names on the episode, to help put misheard names right.
     *
     * @param  array<string, mixed>  $episode
     * @return list<string>
     */
    private function onStageOf(array $episode): array
    {
        $names = [];

        foreach ($episode['People'] ?? [] as $person) {
            if (is_array($person) && filled($person['Name'] ?? null)) {
                $names[] = (string) $person['Name'];
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The summary as the Overview, and its tags added to whatever the episode
     * is already tagged with — or in place of them, when replacing.
     *
     * @param  array<string, mixed>  $episode
     * @return array{Overview: string, Tags: list<string>}
     */
    private function patchFor(array $episode, PlotSummary $reading, bool $replaceTags = false): array
    {
        $existing = ! $replaceTags && is_array($episode['Tags'] ?? null) ? $episode['Tags'] : [];

        return [
            'Overview' => trim((string) $reading->summary),
            'Tags' => array_values(array_unique(array_filter(
                [...$existing, ...$reading->tags],
                fn (mixed $tag): bool => is_string($tag) && filled($tag),
            ))),
        ];
    }
}
