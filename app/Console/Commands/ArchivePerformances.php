<?php

namespace App\Console\Commands;

use App\Enums\PerformanceStatus;
use App\Models\Performance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Move the nights already played out of the house's "still to come".
 *
 * Only a performance the house had vouched for is archived. A draft stays a
 * draft however old: the import registered it off a card and nobody ever
 * confirmed it, so there is no night to have been played — an admin reviewing
 * it later still needs to see it standing where the import left it.
 *
 * Archiving hides nothing. The performance keeps its plans, its staffing and
 * its page; what changes is that the house reads it as history rather than as
 * something still on the bill.
 */
#[Signature('performances:archive
    {--hours=24 : Archive performances that started more than this many hours ago}
    {--dry-run : Name the performances that would be archived without touching anything}')]
#[Description('Archive performances the house vouched for whose night has been played.')]
class ArchivePerformances extends Command
{
    public function handle(): int
    {
        // A night is not history the minute the curtain goes up, so the cutoff
        // sits a day behind: long enough that a show still running is never
        // filed away mid-performance, short enough to mean nothing to a weekly
        // run that will find it either way.
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $archived = 0;

        // Iterate by primary key (keyset pagination): the update writes the very
        // column the query filters on, so an offset-based `chunk()` would walk
        // past rows as the result set shrinks underneath it.
        Performance::query()
            ->playedButNotArchived($cutoff)
            // The stand-in night is a filing drawer, not an evening anybody
            // played — it sits years out, but nothing should archive it even if
            // the house ever moves it.
            ->excludingPlaceholder()
            ->with('format')
            ->lazyById()
            ->each(function (Performance $performance) use ($dryRun, &$archived): void {
                $this->line(sprintf(
                    '  %s %s (%s)',
                    $dryRun ? 'Would archive' : 'Archiving',
                    $performance->displayName(),
                    $performance->startsAt()->format('d.m.Y H:i'),
                ));

                if (! $dryRun) {
                    $performance->update(['status' => PerformanceStatus::Archived]);
                }

                $archived++;
            });

        $this->info(sprintf(
            '%s %d performance(s) that started before %s.',
            $dryRun ? 'Would archive' : 'Archived',
            $archived,
            $cutoff->toIso8601String(),
        ));

        // The line the weekly run is read by. A week that archives nothing in
        // the middle of a season means the import stopped, or the crew stopped
        // vouching for what it brings in.
        Log::info('Performance archiving run finished', [
            'dry_run' => $dryRun,
            'archived' => $archived,
            'older_than_hours' => $hours,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
