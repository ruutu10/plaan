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

/**
 * A performance's date and curtain-up together: "01.09.2026 19:00". Both come
 * off the server already on the venue's clock, so this only joins them — there
 * is deliberately no timezone arithmetic in the browser, whose own clock is
 * whatever the viewer happens to be sitting in.
 */
export function formatEstonianDateTime(
    iso: string | null | undefined,
    startTime: string | null | undefined,
): string {
    const date = formatEstonianDate(iso);

    return startTime ? `${date} ${startTime}` : date;
}
