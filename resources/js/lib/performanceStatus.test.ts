import { describe, expect, it } from 'vitest';
import { acceptsNewPlan, isStillToCome } from '@/lib/performanceStatus';

describe('isStillToCome', () => {
    const now = new Date('2026-07-15T12:00:00Z');

    it('holds for a night later than now, whatever its standing', () => {
        expect(isStillToCome({ startsAt: '2026-07-16T16:00:00Z' }, now)).toBe(
            true,
        );
    });

    it('fails for a night that has started or been played', () => {
        expect(isStillToCome({ startsAt: '2026-07-15T12:00:00Z' }, now)).toBe(
            false,
        );
        expect(isStillToCome({ startsAt: '2026-07-14T16:00:00Z' }, now)).toBe(
            false,
        );
    });
});

describe('acceptsNewPlan', () => {
    const now = new Date('2026-07-15T12:00:00Z');

    it('takes a vouched-for performance still to come', () => {
        expect(
            acceptsNewPlan(
                { status: 'upcoming', startsAt: '2026-07-16T16:00:00Z' },
                now,
            ),
        ).toBe(true);
    });

    it('refuses a draft nobody has reviewed yet', () => {
        expect(
            acceptsNewPlan(
                { status: 'draft', startsAt: '2026-07-16T16:00:00Z' },
                now,
            ),
        ).toBe(false);
    });

    it('refuses a performance that has already been played', () => {
        expect(
            acceptsNewPlan(
                { status: 'upcoming', startsAt: '2026-07-14T16:00:00Z' },
                now,
            ),
        ).toBe(false);
        expect(
            acceptsNewPlan(
                { status: 'archived', startsAt: '2026-07-14T16:00:00Z' },
                now,
            ),
        ).toBe(false);
    });

    it('refuses a performance starting this very instant', () => {
        expect(
            acceptsNewPlan(
                { status: 'upcoming', startsAt: '2026-07-15T12:00:00Z' },
                now,
            ),
        ).toBe(false);
    });
});
