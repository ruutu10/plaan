/** The next performance still to be played. */
export type UpcomingShow = {
    showName: string;
    teamName: string | null;
    /** ISO date (YYYY-MM-DD) the show is staged on, on the venue's clock. */
    date: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
};

/** What the house still has ahead of it, and how ready it is for it. */
export type UpcomingSummary = {
    /** Performances that have not started yet. */
    performances: number;
    /** Those of them nobody has handed a technical plan in for. */
    missingPlans: number;
    next: UpcomingShow | null;
};
