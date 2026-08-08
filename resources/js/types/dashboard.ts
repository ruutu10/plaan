/** The next performance still to be played. */
export type UpcomingFormat = {
    formatName: string;
    teamName: string | null;
    /** ISO date (YYYY-MM-DD) the format is staged on, on the venue's clock. */
    date: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
};

/** What the house still has ahead of it, and how ready it is for it. */
export type UpcomingSummary = {
    /** Performances that have not started yet. */
    performances: number;
    /**
     * Those of them within `planExpectedWithinDays` that nobody has handed a
     * technical plan in for. Nothing is expected of the nights further out.
     */
    missingPlans: number;
    /**
     * How near a performance has to be before its technical plan is expected
     * at all.
     */
    planExpectedWithinDays: number;
    next: UpcomingFormat | null;
};

/**
 * One technical plan handed in for a performance on today's bill. A plan the
 * reader may not open still appears, but carries nothing beyond the fact that
 * it exists — `visible` is false and everything identifying it is null.
 */
export type TodaysPlan = {
    visible: boolean;
    token: string | null;
    /** The plan's own page, or null when the reader may not open it. */
    url: string | null;
    status: string | null;
    statusLabel: string;
    submittedBy: string | null;
};

/** A performance the house is playing today, and the plans written for it. */
export type TodaysPerformance = {
    id: number;
    formatName: string;
    /** The act's own name, on an evening several groups share. */
    title: string | null;
    teamName: string | null;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    /** Empty when nobody has handed a plan in for this performance. */
    plans: TodaysPlan[];
};
