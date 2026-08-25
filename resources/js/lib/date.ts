import { VENUE_TIME_ZONE } from '@/lib/config';

// The server always sends moments in UTC — see `Performance::venueTimezone()`
// for the one place that is allowed to mean anything else — and every
// function below turns one into the venue's wall clock, not the viewer's own.

/**
 * A UTC instant's year/month/day/hour/minute as read on the venue's clock.
 * Null for anything that does not parse — a blank field, a malformed value —
 * so every formatter below can fall back the same way.
 */
function localParts(iso: string | Date): {
    year: string;
    month: string;
    day: string;
    hour: string;
    minute: string;
    second: string;
} | null {
    const instant = iso instanceof Date ? iso : new Date(iso);

    if (Number.isNaN(instant.getTime())) {
        return null;
    }

    const parts = new Intl.DateTimeFormat('en-GB', {
        timeZone: VENUE_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).formatToParts(instant);

    const get = (type: string): string =>
        parts.find((part) => part.type === type)?.value ?? '';

    // Midnight renders as "24:00" under hour12: false in some engines rather
    // than "00:00" — the wall-clock hour is the same moment either way.
    const hour = get('hour');

    return {
        year: get('year'),
        month: get('month'),
        day: get('day'),
        hour: hour === '24' ? '00' : hour,
        minute: get('minute'),
        second: get('second'),
    };
}

/**
 * A UTC instant as the Estonian day.month.year the app shows everywhere,
 * read on the venue's clock — not the viewer's, wherever they happen to be.
 */
export function formatLocalDate(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const parts = localParts(iso);

    return parts ? `${parts.day}.${parts.month}.${parts.year}` : '—';
}

/**
 * A UTC instant's curtain-up as the house says it: "19:00", on the venue's
 * clock.
 */
export function formatLocalTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const parts = localParts(iso);

    return parts ? `${parts.hour}:${parts.minute}` : '—';
}

/**
 * A UTC instant's date and time together, on the venue's clock: "01.09.2026
 * 19:00".
 */
export function formatLocalDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const parts = localParts(iso);

    return parts
        ? `${parts.day}.${parts.month}.${parts.year} ${parts.hour}:${parts.minute}`
        : '—';
}

/**
 * A full UTC instant as "03.08.2026 14:32", on the venue's clock. An alias of
 * {@see formatLocalDateTime} kept as its own name for the moments this
 * reads as a timestamp — when a record was created, when a plan was handed
 * in — rather than a performance's own scheduled time.
 */
export function formatLocalTimestamp(iso: string | null | undefined): string {
    return formatLocalDateTime(iso);
}

/**
 * A moment read to the second on the venue's clock: "20:15:30". The running
 * clock the technician calls cues against, which has to tick in the house's
 * time whatever timezone the browser showing it happens to sit in.
 */
export function formatVenueClockTime(instant: Date): string {
    const parts = localParts(instant);

    return parts ? `${parts.hour}:${parts.minute}:${parts.second}` : '—';
}

/**
 * A moment as a sortable "YYYY-MM-DDTHH:MM:SS" reading of the venue's clock.
 * Two such keys compare as plain strings, which is how a wall-clock time the
 * house wrote down — a curtain-up, a slot's end — is measured against now
 * without either side being dragged through a timezone on the way.
 */
export function venueClockKey(instant: Date): string {
    const parts = localParts(instant);

    return parts
        ? `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`
        : '';
}

/**
 * A UTC instant as the "YYYY-MM-DD" an `<input type="date">` deals in, on the
 * venue's clock — so editing a performance shows the date the house sees it
 * played on, not whatever date the browser's own timezone would land on.
 */
export function toLocalDateInputValue(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const parts = localParts(iso);

    return parts ? `${parts.year}-${parts.month}-${parts.day}` : '';
}
