<?php

namespace App\Console\Commands;

use App\Enums\TechnicalPlanStatus;
use App\Models\TechnicalPlan;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Put the plans of nights already played out of the way.
 *
 * Only the plans the technical team holds are archived — a performer's own
 * draft stays a draft, however old, since nobody ever handed it in. An
 * archived plan is not a hidden one: it still shows in the crew's overview,
 * and it is still offered as the basis for the next plan for the same show.
 */
#[Signature('technical-plans:archive
    {--hours=24 : Archive plans whose performance started more than this many hours ago}
    {--dry-run : Name the plans that would be archived without touching anything}')]
#[Description('Archive technical plans whose performance has already been played.')]
class ArchiveTechnicalPlans extends Command
{
    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $archived = 0;

        // Iterate by primary key (keyset pagination): the update writes the very
        // column the query filters on, so an offset-based `chunk()` would walk
        // past rows as the result set shrinks underneath it.
        TechnicalPlan::query()
            ->whereIn('status', TechnicalPlanStatus::delivered())
            // A plan with no performance has no night to have passed, and
            // `whereHas` leaves it out by itself.
            ->whereHas('performance', fn (Builder $performance) => $performance->where('date', '<', $cutoff))
            ->with('performance.show')
            ->lazyById()
            ->each(function (TechnicalPlan $plan) use ($dryRun, &$archived): void {
                $this->line(sprintf(
                    '  %s %s (%s, %s)',
                    $dryRun ? 'Would archive' : 'Archiving',
                    $plan->token,
                    $plan->performance->show->name,
                    $plan->performance->startsAt()->format('d.m.Y H:i'),
                ));

                if ($dryRun) {
                    $archived++;

                    return;
                }

                Log::debug('Archiving the plan of a performance already played', [
                    'plan_id' => $plan->id,
                    'performance_id' => $plan->performance_id,
                ]);

                $plan->update(['status' => TechnicalPlanStatus::Archived]);
                $archived++;
            });

        $this->info(sprintf(
            '%s %d plan(s) of performances that started before %s.',
            $dryRun ? 'Would archive' : 'Archived',
            $archived,
            $cutoff->toIso8601String(),
        ));

        // The line the daily run is read by. Nothing to archive is the normal
        // case between shows; a season where the tally never moves is the thing
        // worth spotting.
        Log::info('Technical-plan archiving run finished', [
            'dry_run' => $dryRun,
            'archived' => $archived,
            'older_than_hours' => $hours,
            'cutoff' => $cutoff->toIso8601String(),
        ]);

        return self::SUCCESS;
    }
}
