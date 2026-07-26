/** One show as the management screens list and edit it. */
export interface Show {
    id: number;
    name: string;
    description: string | null;
    /** The group whose show this is. Null only for shows nobody claimed yet. */
    teamId: number | null;
    teamName: string | null;
    /** How many dated stagings hang off the show; only the listing counts them. */
    performanceCount: number | null;
}

/** A group the show may be handed to, as offered by the edit form. */
export interface ShowTeamOption {
    id: number;
    name: string;
}
