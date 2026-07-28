/** One show as the management screens list and edit it. */
export interface Show {
    id: number;
    name: string;
    description: string | null;
    /** The group whose show this is. Null only for shows nobody claimed yet. */
    teamId: number | null;
    teamName: string | null;
    /** How many dated performances hang off the show; only the listing counts them. */
    performanceCount: number | null;
}

/** A group the show may be handed to, as offered by the show forms. */
export interface ShowTeamOption {
    id: number;
    name: string;
}

/** The fields a show is written through, and what the server refused about them. */
export interface ShowFormData {
    team_id: number | null;
    name: string;
    description: string;
}

export type ShowFieldErrors = Partial<Record<keyof ShowFormData, string>>;

/** One dated performance of a show. */
export interface Performance {
    id: number;
    /** ISO date (YYYY-MM-DD). */
    date: string;
    /** Minutes, or null when the performance is not timed. */
    duration: number | null;
    /** Plans written for this performance; they outlive it, without a performance. */
    technicalPlanCount: number | null;
}

/** The fields a performance is written through. */
export interface PerformanceFormData {
    date: string;
    duration: number | null;
}

export type PerformanceFieldErrors = Partial<
    Record<keyof PerformanceFormData, string>
>;
