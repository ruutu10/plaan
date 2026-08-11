import { describe, expect, it } from 'vitest';
import {
    formatLocalDate,
    formatLocalDateTime,
    formatLocalTime,
    formatLocalTimestamp,
    toLocalDateInputValue,
} from '@/lib/date';

describe('formatLocalDate', () => {
    it('reads the UTC instant on the venue clock', () => {
        // 21:30 UTC in July is 00:30 the next day in Tallinn (UTC+3, DST).
        expect(formatLocalDate('2026-07-15T21:30:00Z')).toBe('16.07.2026');
    });

    it('stands in for a record the server has not saved yet', () => {
        expect(formatLocalDate(null)).toBe('—');
        expect(formatLocalDate(undefined)).toBe('—');
    });
});

describe('formatLocalTime', () => {
    it('reads the UTC instant on the venue clock', () => {
        expect(formatLocalTime('2026-07-15T16:00:00Z')).toBe('19:00');
    });

    it('stands in for a record the server has not saved yet', () => {
        expect(formatLocalTime(null)).toBe('—');
    });
});

describe('formatLocalDateTime and formatLocalTimestamp', () => {
    it('read the UTC instant on the venue clock, offset applied once', () => {
        // The server sends true UTC; the venue's own offset is applied here,
        // not carried over from the wire — a test run in any timezone gets
        // the same answer.
        expect(formatLocalDateTime('2026-07-15T06:30:00Z')).toBe(
            '15.07.2026 09:30',
        );
        expect(formatLocalTimestamp('2026-07-15T06:30:00Z')).toBe(
            '15.07.2026 09:30',
        );
    });

    it('cross the venue-local midnight the UTC day does not', () => {
        // 22:15 UTC in January is 00:15 the next day in Tallinn (UTC+2).
        expect(formatLocalDateTime('2026-01-15T22:15:00Z')).toBe(
            '16.01.2026 00:15',
        );
    });

    it('stand in for a record the server has not saved yet', () => {
        expect(formatLocalDateTime(null)).toBe('—');
        expect(formatLocalTimestamp(undefined)).toBe('—');
    });
});

describe('toLocalDateInputValue', () => {
    it('reads the date an <input type="date"> expects, on the venue clock', () => {
        expect(toLocalDateInputValue('2026-01-15T22:15:00Z')).toBe(
            '2026-01-16',
        );
    });

    it('stands in for a record the server has not saved yet', () => {
        expect(toLocalDateInputValue(null)).toBe('');
    });
});
