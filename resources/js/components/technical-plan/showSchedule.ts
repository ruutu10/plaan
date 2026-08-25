import { venueClockKey } from '@/lib/date';
import type { PlanMeta } from '@/types/technicalPlan';

/**
 * The slot a show is expected to fill, as the technician's view shows it under
 * the wall clock: when the curtain went up and when the act is due to be off.
 */
export interface ShowSchedule {
    /** Curtain-up on the venue's clock, "19:00". */
    start: string;
    /**
     * When the slot runs out, "20:15". Null when the plan names no duration —
     * there is no end to hold the show to, so none is invented.
     */
    end: string | null;
    /** Whether the show is past its slot: the tech is running over. */
    overrunning: boolean;
}

const MINUTES_PER_DAY = 24 * 60;

/**
 * Work out a show's slot from what the plan says about it. Everything here is
 * the venue's wall clock — `meta.startTime` and `meta.performanceDate` arrive
 * on it, and `now` is read onto it — so a late show crossing midnight still
 * measures right, and a browser sitting in another timezone changes nothing.
 *
 * Null when the plan gives no start time, which leaves nothing to show.
 */
export function showSchedule(meta: PlanMeta, now: Date): ShowSchedule | null {
    const start = minutesOfDay(meta.startTime);

    if (start === null) {
        return null;
    }

    // A draft restored from the browser can carry the minutes back as a string.
    const duration = Number(meta.duration);

    if (!Number.isFinite(duration) || duration <= 0) {
        return { start: clock(start), end: null, overrunning: false };
    }

    const end = start + Math.round(duration);

    return {
        start: clock(start),
        end: clock(end % MINUTES_PER_DAY),
        overrunning: hasPassed(meta.performanceDate, end, now),
    };
}

/** "19:00" — or "19:00:00" as some sources write it — as minutes past midnight. */
function minutesOfDay(time: string | null | undefined): number | null {
    const match = /^(\d{1,2}):(\d{2})/.exec((time ?? '').trim());

    if (!match) {
        return null;
    }

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return hours * 60 + minutes;
}

/** Minutes past midnight back as the "20:15" the house reads off a clock. */
function clock(minutes: number): string {
    const hours = Math.floor(minutes / 60);

    return `${pad(hours)}:${pad(minutes % 60)}`;
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/**
 * Whether the venue's clock has gone past the show's end. The end is a wall
 * time on the performance's own date, so a plan that names no date — a draft
 * not yet tied to a night — is never called overrunning: there is no day to
 * hold the time against.
 */
function hasPassed(
    performanceDate: string,
    endMinutes: number,
    now: Date,
): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(performanceDate)) {
        return false;
    }

    // A show starting at 23:30 ends on the following day's clock.
    const endDate = addDays(
        performanceDate,
        Math.floor(endMinutes / MINUTES_PER_DAY),
    );

    return (
        venueClockKey(now) >
        `${endDate}T${clock(endMinutes % MINUTES_PER_DAY)}:00`
    );
}

/** A "YYYY-MM-DD" moved on by whole days, month and year ends included. */
function addDays(date: string, days: number): string {
    if (days === 0) {
        return date;
    }

    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days))
        .toISOString()
        .slice(0, 10);
}
