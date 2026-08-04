/**
 * What put a record on the books: somebody typing it in, or the weekly reading
 * of the Planka board. Read-only everywhere — the screens report it, the server
 * decides it.
 */
export type CreatedBy = 'manual' | 'planka-import';

/** One format as the management screens list and edit it. */
export interface Format {
    id: number;
    name: string;
    description: string | null;
    /** The group whose format this is. Null only for formats nobody claimed yet. */
    teamId: number | null;
    teamName: string | null;
    /** How many dated performances hang off the format; only the listing counts them. */
    performanceCount: number | null;
    /**
     * Whether the user may correct the format itself. False for a format they only
     * reach because one of their groups plays a performance of it.
     */
    canEdit: boolean;
    /**
     * How many readings of Planka cards stand behind this format — one per card
     * that made it or added a night to it. Zero for a format entered by hand, and
     * for a user who may not read them.
     */
    reasoningLogCount: number;
    /** Whether the format was entered by hand or read off a Planka card. */
    createdBy: CreatedBy;
    /**
     * When the format was put on the books; ISO 8601, already on the venue's
     * clock. Null only for a format the server has not saved yet.
     */
    createdAt: string | null;
}

/** What the AI made of the Planka card an imported record came from. */
export interface ClaudeReasoningLog {
    id: number;
    /** The card on the board, when the reading came from one. */
    cardId: string | null;
    cardName: string | null;
    /** That card on the board, ready to open. Null when none is configured. */
    cardUrl: string | null;
    /** One line per decision, in the model's own words. */
    notes: string[];
    /** When the card was read; ISO 8601. */
    readAt: string | null;
}

/** A group the format may be handed to, as offered by the format forms. */
export interface FormatTeamOption {
    id: number;
    name: string;
}

/** The fields a format is written through, and what the server refused about them. */
export interface FormatFormData {
    team_id: number | null;
    name: string;
    description: string;
}

export type FormatFieldErrors = Partial<Record<keyof FormatFormData, string>>;

/** One dated performance of a format. */
export interface Performance {
    id: number;
    /**
     * The act's own name, for an evening several groups share. Null when the
     * format's own name already says what is played.
     */
    title: string | null;
    /**
     * The group playing this performance, when it is not the format's own. Null
     * for the ordinary performance, which the format's group plays.
     */
    teamId: number | null;
    teamName: string | null;
    /**
     * ISO date (YYYY-MM-DD), on the venue's clock. The date and the start time
     * are one stored moment server-side; they are split for the form.
     */
    date: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    /** Minutes, or null when the performance is not timed. */
    duration: number | null;
    /**
     * Imported and not reviewed yet. A draft is kept out of the listing technical
     * plans are written from until somebody clears it here.
     */
    isDraft: boolean;
    /** Plans written for this performance; they outlive it, without a performance. */
    technicalPlanCount: number | null;
    /**
     * Whether the reading of the Planka card that registered this performance
     * can be read: one card, so never more than one. Zero for a performance
     * entered by hand, and for a user who may not read it.
     */
    reasoningLogCount: number;
    /** The card on the Planka board this performance was announced on. */
    plankaCardId: string | null;
    /** That card on the board, ready to open. Null when none is configured. */
    plankaCardUrl: string | null;
    /** Whether the performance was entered by hand or read off a Planka card. */
    createdBy: CreatedBy;
    /**
     * When the performance was put on the books; ISO 8601, already on the
     * venue's clock. Not the date it is played — that is `date` above.
     */
    createdAt: string | null;
}

/**
 * One row of the crew's overview of every performance in the house. Narrower
 * than {@link Performance}: the overview only reads, and a performance is
 * corrected on the format it belongs to.
 */
export interface AdminPerformanceRow {
    id: number;
    /** The format the row opens — where the performance is edited. */
    formatId: number;
    formatName: string;
    /** The act's own name, for an evening several groups share. */
    title: string | null;
    /** Who plays it: the performance's own group, or the format's. */
    teamName: string | null;
    /** ISO date (YYYY-MM-DD), on the venue's clock. */
    date: string;
    /** Curtain-up as "19:00", on the venue's clock. */
    startTime: string;
    /** Minutes, or null when the performance is not timed. */
    duration: number | null;
    /** Imported and not reviewed yet. */
    isDraft: boolean;
    technicalPlanCount: number | null;
}

/** The fields a performance is written through. */
export interface PerformanceFormData {
    /** The act's own name; empty leaves the performance under the format's. */
    title: string;
    /** The performing group; null leaves the performance to the format's own. */
    team_id: number | null;
    date: string;
    /** "19:00" on the venue's clock; empty falls back to the house's usual hour. */
    start_time: string;
    duration: number | null;
    is_draft: boolean;
    /** Empty for a performance that is not on the board at all. */
    planka_card_id: string;
}

export type PerformanceFieldErrors = Partial<
    Record<keyof PerformanceFormData, string>
>;
