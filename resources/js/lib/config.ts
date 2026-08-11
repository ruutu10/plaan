/**
 * The wall clock the house runs on — the frontend's own copy of
 * `config('performance.timezone')` (see `Performance::venueTimezone()`).
 * Every function that turns a UTC instant into a local date or time reads
 * this rather than naming a zone itself.
 */
export const VENUE_TIME_ZONE = 'Europe/Tallinn';
