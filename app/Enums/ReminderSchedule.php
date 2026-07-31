<?php

namespace App\Enums;

use App\Concerns\HasValues;
use App\Models\Performance;
use App\Models\PerformanceReminder;
use Carbon\CarbonInterface;
use Carbon\CarbonInterval;

/**
 * The moments before a performance at which its performers are reminded that
 * the technical plan is still missing. Both reminders carry the same copy: the
 * second is not a sterner letter, it is the same letter arriving when there is
 * no longer time to forget about it.
 *
 * Adding a case here adds a reminder — a performance that has never been
 * looked at for it has no {@see PerformanceReminder} row, so the
 * scheduled run picks it up on its own.
 */
enum ReminderSchedule: string
{
    use HasValues;

    /** A week out, near enough: time to write the plan without hurrying. */
    case SixDays = 'six_days';

    /** The last useful moment — after this the crew is setting up regardless. */
    case ThirtyHours = 'thirty_hours';

    /**
     * How far ahead of the performance this reminder goes out.
     */
    public function lead(): CarbonInterval
    {
        return match ($this) {
            self::SixDays => CarbonInterval::days(6),
            self::ThirtyHours => CarbonInterval::hours(30),
        };
    }

    /**
     * When this reminder falls due for a performance starting at the given
     * moment.
     */
    public function dueAt(CarbonInterface $startsAt): CarbonInterface
    {
        return $startsAt->avoidMutation()->sub($this->lead());
    }

    /**
     * The latest a performance can start and still be inside this reminder's
     * window as of `$now` — the same comparison as {@see dueAt()}, turned round
     * so a run can ask the database for the performances that are due rather
     * than reading every upcoming one and working it out in PHP.
     */
    public function dueForPerformancesStartingBy(CarbonInterface $now): CarbonInterface
    {
        return $now->avoidMutation()->add($this->lead());
    }

    /**
     * How the reminder reads to the performer: how long they have left. The
     * mail says this rather than a date, because "kuue päeva pärast" is what
     * makes somebody open the wizard.
     */
    public function noticeLabel(): string
    {
        return match ($this) {
            self::SixDays => 'kuue päeva pärast',
            self::ThirtyHours => 'juba homme',
        };
    }

    /**
     * The cases in the order they fall due, furthest from the performance
     * first — the order a run should deal with them in.
     *
     * @return array<int, self>
     */
    public static function inOrder(): array
    {
        return [self::SixDays, self::ThirtyHours];
    }

    /**
     * Why this reminder should be recorded as dealt with rather than sent, or
     * null when it should go out.
     *
     * Both answers come down to the same thing: a reminder is only worth
     * sending while it is still the reminder that fits. There are two ways it
     * can stop being that.
     *
     * @return 'registered_too_late'|'overtaken'|null
     */
    public function writeOffReasonFor(Performance $performance, CarbonInterface $now): ?string
    {
        // The night was put on the books after this reminder was already behind
        // it. A performance registered three days before it happens never had a
        // six-day window, and chasing it for one would be mailing about a
        // deadline nobody could have met.
        if ($performance->created_at?->greaterThan($this->dueAt($performance->date))) {
            return 'registered_too_late';
        }

        // A later reminder is due as well, so this one has been overtaken. This
        // is what a run catching up after downtime hits: without it, a
        // performance whose every moment fell due while nothing was running
        // would get both letters at once — and two identical mails arriving
        // together is how a reminder teaches people to ignore it.
        return $this->overtakenFor($performance, $now) ? 'overtaken' : null;
    }

    /**
     * Whether a reminder that falls due later than this one is already due too.
     */
    private function overtakenFor(Performance $performance, CarbonInterface $now): bool
    {
        $mine = $this->dueAt($performance->date);

        foreach (self::cases() as $other) {
            $theirs = $other->dueAt($performance->date);

            if ($theirs->greaterThan($mine) && $now->greaterThanOrEqualTo($theirs)) {
                return true;
            }
        }

        return false;
    }
}
