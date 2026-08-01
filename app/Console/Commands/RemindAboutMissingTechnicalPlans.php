<?php

namespace App\Console\Commands;

use App\Actions\RemindAboutTechnicalPlan;
use App\Enums\ReminderSchedule;
use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Chase the performers of every upcoming night the technical team still has no
 * plan for.
 *
 * Meant to run often — hourly — and to do nothing most of the time. What has
 * already been dealt with is recorded per performance and per
 * {@see ReminderSchedule} moment, so running it twice in a minute, or catching
 * up after a day of downtime, sends each reminder exactly once.
 */
#[Signature('performances:remind-missing-plans
    {--dry-run : Report who would be chased without mailing anybody or recording anything}
    {--backfill : Record every reminder that is due right now as dealt with, without mailing — for switching the reminders on without chasing a season\'s worth of performances at once}')]
#[Description('Remind performers about the technical plans of upcoming performances that have not been handed in.')]
class RemindAboutMissingTechnicalPlans extends Command
{
    public function handle(RemindAboutTechnicalPlan $remind): int
    {
        if (! config('performance.reminders.enabled')) {
            $this->info('Technical-plan reminders are switched off; nothing was sent.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $backfill = (bool) $this->option('backfill');
        $now = now();

        $chased = 0;
        $mailed = 0;
        $recorded = 0;

        foreach (ReminderSchedule::inOrder() as $schedule) {
            $due = $this->due($schedule, $now);

            foreach ($due as $performance) {
                $chased++;

                $this->line(sprintf(
                    '  %s %s (%s, %s): %s',
                    $this->verb($dryRun, $backfill),
                    $performance->show->name,
                    $performance->startsAt()->format('d.m.Y H:i'),
                    $schedule->value,
                    $this->audience($performance),
                ));

                if ($dryRun) {
                    continue;
                }

                if ($backfill) {
                    $performance->reminders()->create(['schedule' => $schedule]);
                    $recorded++;

                    continue;
                }

                $mailed += $remind->handle($performance, $schedule);
            }
        }

        $this->info($this->sentence($dryRun, $backfill, $chased, $mailed, $recorded));

        // The line an hourly job is read by. A run that chased nobody is the
        // normal case; a season where nothing is ever chased is the thing worth
        // spotting, and only the tally shows it.
        Log::info('Technical-plan reminder run finished', [
            'dry_run' => $dryRun,
            'backfill' => $backfill,
            'performances_due' => $chased,
            'performers_mailed' => $mailed,
            'reminders_backfilled' => $recorded,
        ]);

        return self::SUCCESS;
    }

    /**
     * The performances this reminder is due for right now: still to be played,
     * vouched for, owned by a group there is somebody to write to, without a
     * plan the technical team holds, and not already dealt with at this moment.
     *
     * The window is expressed as a bound on the start time rather than worked
     * out per row, so the database hands back the few that matter instead of
     * every performance of the season.
     *
     * @return Collection<int, Performance>
     */
    private function due(ReminderSchedule $schedule, CarbonInterface $now): Collection
    {
        return Performance::query()
            ->vouchedFor()
            // Still ahead of us, and near enough for this reminder to be due.
            ->where('date', '>', $now)
            ->where('date', '<=', $schedule->dueForPerformancesStartingBy($now))
            // Nobody to chase without a group — the performance's own, or the
            // show's when the evening is not a shared one.
            ->where(fn (Builder $performance) => $performance
                ->whereNotNull('performances.team_id')
                ->orWhereHas('show', fn (Builder $show) => $show->whereNotNull('team_id')))
            ->whereDoesntHave(
                'reminders',
                fn (Builder $reminders) => $reminders->where('schedule', $schedule),
            )
            ->whereDoesntHave(
                'technicalPlans',
                fn (Builder $plans) => $plans->whereIn('status', TechnicalPlanStatus::delivered()),
            )
            ->with(['team.members', 'show.team.members'])
            ->orderBy('date')
            ->get();
    }

    /**
     * How many performers a performance would be chased through, for the
     * run's output.
     */
    private function audience(Performance $performance): string
    {
        $members = $performance->performedBy()?->members->count() ?? 0;

        return $members === 0
            ? 'nobody — the group has no members'
            : sprintf('%d performer(s)', $members);
    }

    /**
     * What this run is doing to each performance it found.
     */
    private function verb(bool $dryRun, bool $backfill): string
    {
        return match (true) {
            $dryRun => 'Would chase',
            $backfill => 'Writing off',
            default => 'Chasing',
        };
    }

    /**
     * The run's closing line, for whoever is at the terminal.
     */
    private function sentence(bool $dryRun, bool $backfill, int $chased, int $mailed, int $recorded): string
    {
        if ($dryRun) {
            return sprintf('%d performance(s) would be chased for a missing technical plan.', $chased);
        }

        if ($backfill) {
            return sprintf('Wrote off %d due reminder(s) without mailing anybody.', $recorded);
        }

        return sprintf(
            'Chased %d performance(s) for a missing technical plan; %d performer(s) mailed.',
            $chased,
            $mailed,
        );
    }
}
