/**
 * An ISO date (YYYY-MM-DD) as the Estonian day.month.year the app shows
 * everywhere. Anything that is not an ISO date is handed back untouched.
 */
export function formatEstonianDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const parts = iso.split('-');

    return parts.length === 3 ? `${parts[2]}.${parts[1]}.${parts[0]}` : iso;
}
