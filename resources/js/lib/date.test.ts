import { describe, expect, it } from 'vitest';
import { formatEstonianTimestamp } from '@/lib/date';

describe('formatEstonianTimestamp', () => {
    it('reads the moment off the string rather than through a clock', () => {
        // The server sends it on the venue's clock; the offset is along for the
        // ride and is deliberately not applied a second time here. A test run
        // in any timezone gets the same answer.
        expect(formatEstonianTimestamp('2026-07-15T09:30:00+03:00')).toBe(
            '15.07.2026 09:30',
        );
    });

    it('stands in for a record the server has not saved yet', () => {
        expect(formatEstonianTimestamp(null)).toBe('—');
        expect(formatEstonianTimestamp(undefined)).toBe('—');
    });
});
