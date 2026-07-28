/** The next performance still to be played. */
export type UpcomingShow = {
    showName: string;
    teamName: string | null;
    /** ISO date (YYYY-MM-DD) the show is staged on. */
    date: string;
};

/** What the house still has ahead of it, and how ready it is for it. */
export type UpcomingSummary = {
    /** Performances from today on. */
    performances: number;
    /** Those of them nobody has handed a technical plan in for. */
    missingPlans: number;
    next: UpcomingShow | null;
};
