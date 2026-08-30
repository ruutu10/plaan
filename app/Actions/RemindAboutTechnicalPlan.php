<?php

namespace App\Actions;

use App\Http\Controllers\PerformanceReminderController;
use App\Models\Performance;
use App\Models\User;
use App\Notifications\TechnicalPlanMissing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Chase the performers of one night for the technical plan nobody has handed
 * in.
 *
 * Nothing here decides who should hear about it or whether it is time: a member
 * of the crew opens the performance, picks the people, and presses send — see
 * {@see PerformanceReminderController}. What is settled
 * here is that every recipient gets a letter of their own, because the link it
 * carries signs its holder in as them.
 */
class RemindAboutTechnicalPlan
{
    public function __construct(private BuildTechnicalPlanInvite $invite) {}

    /**
     * Write to each of the chosen performers, and return how many were mailed.
     *
     * @param  Collection<int, User>  $performers  the people to chase; already checked to be of the group playing this performance
     */
    public function handle(Performance $performance, Collection $performers, User $sentBy): int
    {
        foreach ($performers as $performer) {
            $performer->notify(new TechnicalPlanMissing(
                $performance,
                // The link signs its recipient in, so each performer gets one
                // of their own rather than a shared one.
                $this->invite->handle($performer, $performance),
            ));
        }

        // A performance played to a plan nobody was asked for is the failure
        // this exists to prevent, and a reminder is now somebody's decision
        // rather than a job's, so both the send and the sender are on the record.
        Log::notice('Performers chased by hand for a missing technical plan', [
            'performance_id' => $performance->id,
            'format_id' => $performance->format_id,
            'starts_at' => $performance->startsAt()->toDateTimeString(),
            'recipients' => $performers->count(),
            'recipient_ids' => $performers->map(fn (User $performer): int => $performer->id)->values()->all(),
            'user_id' => $sentBy->id,
        ]);

        return $performers->count();
    }
}
