<?php

namespace App\Actions;

use App\Console\Commands\RemindAboutMissingTechnicalPlans;
use App\Enums\ReminderSchedule;
use App\Models\Performance;
use App\Models\PerformanceReminder;
use App\Models\User;
use App\Notifications\TechnicalPlanMissing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Chase the performers of one night for the technical plan nobody has handed
 * in, for one of the {@see ReminderSchedule} moments.
 *
 * Whether the reminder is *due* is the caller's question — see
 * {@see RemindAboutMissingTechnicalPlans}. What is
 * settled here is that it happens exactly once: the record of having dealt with
 * this performance at this moment is written before any mail goes out, so a run
 * that dies halfway leaves a night un-chased rather than chasing it twice on
 * the next pass. Nothing is more likely to be ignored than a reminder that
 * arrives in duplicate.
 */
class RemindAboutTechnicalPlan
{
    public function __construct(private BuildTechnicalPlanInvite $invite) {}

    /**
     * Deal with one performance at one reminder moment. Returns the performers
     * mailed — zero when the moment was written off, or when somebody else got
     * there first.
     */
    public function handle(Performance $performance, ReminderSchedule $schedule): int
    {
        $reminder = $this->claim($performance, $schedule);

        if ($reminder === null) {
            return 0;
        }

        $writeOff = $schedule->writeOffReasonFor($performance, now());

        if ($writeOff !== null) {
            Log::info('Wrote off a technical-plan reminder rather than sending it late', [
                'performance_id' => $performance->id,
                'schedule' => $schedule->value,
                'reason' => $writeOff,
                'starts_at' => $performance->startsAt()->toDateTimeString(),
                'due_at' => $schedule->dueAt($performance->date)->toDateTimeString(),
                'registered_at' => $performance->created_at?->toDateTimeString(),
            ]);

            return 0;
        }

        $performers = $this->performers($performance);

        if ($performers->isEmpty()) {
            // The format has an owning group, but the group has nobody in it, so
            // there is no one to write to. The claim is given back: this is a
            // gap in the records rather than a decision, and the reminder
            // should still go out if somebody joins the group in time.
            $reminder->delete();

            Log::warning('A performance needs its technical plan chased, but its group has no members', [
                'performance_id' => $performance->id,
                'format_id' => $performance->format_id,
                'team_id' => $performance->performingTeamId(),
                'schedule' => $schedule->value,
            ]);

            return 0;
        }

        foreach ($performers as $performer) {
            $performer->notify(new TechnicalPlanMissing(
                $performance,
                $schedule,
                // The link signs its recipient in, so each performer gets one
                // of their own rather than a shared one.
                $this->invite->handle($performer, $performance),
            ));
        }

        $notifiedTech = $this->tellTheCrew($performance, $schedule, $performers);

        $reminder->forceFill([
            'sent_at' => now(),
            'recipients' => $performers->count(),
        ])->save();

        // A performance played to a plan nobody was asked for is the failure
        // this whole job exists to prevent, so every send is on the record.
        Log::info('Chased the performers for a missing technical plan', [
            'performance_id' => $performance->id,
            'format_id' => $performance->format_id,
            'schedule' => $schedule->value,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'recipients' => $performers->count(),
            'notified_tech' => $notifiedTech,
        ]);

        return $performers->count();
    }

    /**
     * Send the technical team its own copy: the same night, the same missing
     * plan, and the roster of who was chased about it.
     *
     * Deliberately a separate message rather than a copy of a performer's. Each
     * performer's letter carries a link that signs the holder in as them, so
     * putting anybody else on it — even the house's own crew — would be handing
     * out a credential. The crew's copy carries no such link.
     *
     * @param  Collection<int, User>  $performers
     */
    private function tellTheCrew(Performance $performance, ReminderSchedule $schedule, Collection $performers): bool
    {
        $techEmail = (string) config('technical_plan.tech_email');

        if ($techEmail === '') {
            Log::warning('No technical contact configured; a missing plan was chased without the crew hearing about it', [
                'performance_id' => $performance->id,
                'schedule' => $schedule->value,
            ]);

            return false;
        }

        Notification::route('mail', $techEmail)->notify(new TechnicalPlanMissing(
            $performance,
            $schedule,
            chased: array_values($performers
                ->map(fn (User $performer): array => [
                    'name' => $performer->name,
                    'email' => $performer->email,
                ])
                ->all()),
        ));

        return true;
    }

    /**
     * Record that this performance has been dealt with at this moment, or
     * return null when a row was already there.
     *
     * The unique key on the table is what actually decides it: two runs
     * overlapping is the case this guards against, and only the database can
     * settle that race.
     */
    private function claim(Performance $performance, ReminderSchedule $schedule): ?PerformanceReminder
    {
        try {
            return $performance->reminders()->create(['schedule' => $schedule]);
        } catch (QueryException $exception) {
            Log::debug('A technical-plan reminder was already claimed by another run', [
                'performance_id' => $performance->id,
                'schedule' => $schedule->value,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Who hears about it: every member of the group playing this performance —
     * its own when the evening is shared, the format's otherwise. A performance
     * neither has a group for has nobody to chase; the caller filters those
     * out, and this returns nothing for them either way.
     *
     * @return Collection<int, User>
     */
    private function performers(Performance $performance): Collection
    {
        $members = $performance->performedBy()?->members;

        return $members === null
            ? new Collection
            : $members->toBase();
    }
}
