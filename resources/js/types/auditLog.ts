/**
 * One row of the audit-log feed: what happened, to what, and who did it. See
 * App\Concerns\LogsModelActivity for how the trail is written.
 */
export interface AuditLogEntry {
    id: number;
    /** e.g. "created", "updated", "deleted", "submitted", "status_changed". */
    event: string | null;
    /** The full sentence: what happened, and who did it. */
    description: string;
    /** The kind of record this was about; null if the record type is gone. */
    subjectType: string | null;
    subjectId: number | string | null;
    /**
     * The name the subject reads by — a team or user's own name, a
     * performance's title (its format's, when it carries none of its own).
     * Null for a subject type not worth naming this way; the row falls back
     * to `subjectType` and `subjectId`.
     */
    subjectLabel: string | null;
    /** Null reads as the system itself — nobody was signed in to do this. */
    causerName: string | null;
    /** ISO 8601. */
    createdAt: string | null;
}
