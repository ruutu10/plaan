<?php

namespace App\Console\Commands;

use App\Enums\PerformanceStaffRole;
use App\Models\Performance;
use App\Notifications\TechnicianMissing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Chase the technical team about the upcoming nights nobody has signed on to
 * run sound and light for.
 *
 * Meant to run daily, and — unlike {@see RemindAboutMissingTechnicalPlans} —
 * to keep chasing: nothing here is recorded as dealt with, so a performance
 * stays on tomorrow's digest for as long as it is inside the lead window and
 * still has no {@see PerformanceStaffRole::Technician} among its {@see
 * Performance::staff()}, and drops off the moment one signs on or the night
 * is played. One mail names every performance still open, rather than one
 * mail per performance: the reader is the technical team itself, so there is
 * nobody a shared letter could put a credential in front of.
 */
#[Signature('performances:remind-missing-technicians
    {--dry-run : List who would be chased without mailing anybody}')]
#[Description('Remind the technical team about upcoming performances nobody has signed on to run sound and light for.')]
class RemindAboutMissingTechnicians extends Command
{
    public function handle(): int
    {
        if (! config('performance.technician_reminders.enabled')) {
            $this->info('Missing-technician reminders are switched off; nothing was sent.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $performances = $this->due();

        if ($performances->isEmpty()) {
            $this->info('No upcoming performance is missing a technician.');

            return self::SUCCESS;
        }

        foreach ($performances as $performance) {
            $this->line(sprintf(
                '  %s %s (%s)',
                $dryRun ? 'Would chase' : 'Chasing',
                $performance->title === null ? $performance->format->name : $performance->format->name.' — '.$performance->title,
                $performance->startsAt()->format('d.m.Y H:i'),
            ));
        }

        if ($dryRun) {
            $this->info(sprintf('%d performance(s) would be chased for a missing technician.', $performances->count()));

            return self::SUCCESS;
        }

        $techEmail = (string) config('technical_plan.tech_email');

        if ($techEmail === '') {
            $this->warn('No technical contact configured; nobody was mailed.');

            Log::warning('Missing-technician reminder run found performances to chase, but no technical contact is configured', [
                'performances' => $performances->count(),
            ]);

            return self::SUCCESS;
        }

        Notification::route('mail', $techEmail)->notify(new TechnicianMissing($performances));

        $this->info(sprintf('Chased the technical team about %d performance(s) missing a technician.', $performances->count()));

        // The line the daily run is read by. A digest going out is the normal
        // case whenever a gap exists; a season where it is never empty is the
        // thing worth spotting, and only the tally shows it.
        Log::info('Missing-technician reminder run finished', [
            'dry_run' => $dryRun,
            'performances_due' => $performances->count(),
        ]);

        return self::SUCCESS;
    }

    /**
     * The performances currently missing a technician: still to be played,
     * vouched for, near enough for the configured lead time, and without a
     * {@see PerformanceStaffRole::Technician} among their staff.
     *
     * @return Collection<int, Performance>
     */
    private function due(): Collection
    {
        $now = now();
        $leadDays = max(1, (int) config('performance.technician_reminders.lead_days', 7));

        return Performance::query()
            ->vouchedFor()
            ->where('date', '>', $now)
            ->where('date', '<=', $now->copy()->addDays($leadDays))
            ->whereDoesntHave(
                'staff',
                fn (Builder $staff) => $staff->where('performance_staff.role', PerformanceStaffRole::Technician),
            )
            ->with('format')
            ->orderBy('date')
            ->get();
    }
}
